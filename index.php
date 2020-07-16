<?php
session_start();
require('dbconnect.php');
require_once('functions.php');


//idがセッションに記録されてる＆最後のログインから１時間以内であるか確認
if(isset($_SESSION['id']) && $_SESSION['time'] + 3600 > time()) {
    //ログインしている
    $_SESSION['time'] = time(); //今の時間で上書きし最後のログインを記録！

    $members = $db->prepare('SELECT * FROM members WHERE id=?');
    $members->execute(array($_SESSION['id']));
    $member = $members->fetch();
} else {
    //ログインしていない！
    header('Location: login.php');
    exit();
}


//(res(返信))のパラメータ値チェック
if(isset($_POST['res'])){
    $res_ck = mb_convert_kana($_POST['res'], 'n', 'UTF-8');
    if(v1($res_ck) || v2($res_ck) || v3($res_ck) || !ctype_digit($res_ck)){
        echo "不正な値が入力されたので中断しました";
        exit();
    }
}
//reply_post_id(res(返信))のパラメータ値チェック
if(isset($_POST['reply_post_id'])){
    if($_POST['reply_post_id'] !== "none"){
    $reply_post_id_ck = mb_convert_kana($_POST['reply_post_id'], 'n', 'UTF-8');
        if(v1($reply_post_id_ck) || v2($reply_post_id_ck) || v3($reply_post_id_ck) || !ctype_digit($reply_post_id_ck)){
            echo "不正な値が入力されたので中断しました";
            exit();
        }
    }
}
//ページ数のパラメータ値チェック
if(isset($_REQUEST['page'])){
    $page_ck = mb_convert_kana($_REQUEST['page'], 'n', 'UTF-8');
    if(v1($page_ck) || v2($page_ck) || v3($page_ck) || !ctype_digit($page_ck)){
        echo "不正な値が入力されたので中断しました";
        exit();
    }
}


//投稿を記録する！
if(!empty($_POST)) {
    if(isset($_POST['message'])) {
        //reply_post_idが "none" なら0を入れる(DBに登録できない為)
        if($_POST['reply_post_id'] === "none"){ 
            $_POST['reply_post_id'] = 0;
        }
        $message = $db->prepare(
            'INSERT INTO posts
             SET 
                member_id=?, 
                message=?, 
                reply_post_id=?,
                retweet_post_id=0,
                retweet_member_id=0,
                delete_flg=0, 
                created=NOW()'
            );
        $message->execute(array(
            $member['id'],
            $_POST['message'],
            $_POST['reply_post_id']
        ));

        header('Location: index.php');
        exit();
    }
}


//投稿を取得する！
if(!isset($_REQUEST['page']) || $_REQUEST['page'] === ''){
    $page = 1;
} else {
    $page = $_REQUEST['page'];
}
$page = max($page, 1);

    //最終ページを取得する
    $counts = $db->query('SELECT COUNT(*) AS cnt FROM posts WHERE delete_flg=0');
    $cnt = $counts->fetch();
    $maxPage = ceil($cnt['cnt'] / 5);
    $page = min($page, $maxPage);

    $start = ($page - 1) * 5;

    $posts = $db->prepare('SELECT m.name, m.picture, p.* FROM members m, posts p WHERE m.id=p.member_id ORDER BY p.created DESC LIMIT ?, 5');
    $posts->bindParam(1, $start, PDO::PARAM_INT);
    $posts->execute();

//投稿取得
$posts = $db->prepare(
    'SELECT 
        m.name,
        m.picture,
        p.id,
        p.message,
        p.member_id,
        p.reply_post_id,
        p.retweet_post_id,
        p.retweet_member_id,
        p.created,
        p.modified
     FROM 
        members m,
        posts p
     WHERE 
        m.id=p.member_id AND delete_flg=0
     GROUP BY 
        p.id
     ORDER BY p.created
     DESC LIMIT ?, 5'
     );
$posts->bindParam(1, $start, PDO::PARAM_INT);
$posts->execute();


//ログイン者が「いいね」した投稿IDとポストIDを取得（ボタン色を変えるクラス指定に使用）
    $ine_posts = $db->prepare('SELECT posts_id, id FROM rt_ine WHERE ine=1 && member_id=?');
    $ine_posts->execute(array(
        $member['id']
    ));
        $rows = $ine_posts->fetchAll(PDO::FETCH_KEY_PAIR);

//ログイン者が「リツイート」した投稿IDとポストIDを取得（ボタン色を変えるクラス指定に使用）
    $rt_posts = $db->prepare('SELECT posts_id, id FROM rt_ine WHERE rt=1 && member_id=?');
    $rt_posts->execute(array(
        $member['id']
    ));
        $rt_rows = $rt_posts->fetchAll(PDO::FETCH_KEY_PAIR);

//リツイートされたポストには元のカウントを表示したいので、リツイートされてるIDを配列へ入れておく
    $rt_cnt_orig = $db->query(
        'SELECT 
            id,
            retweet_post_id
        FROM 
            posts
        WHERE 
            retweet_post_id > 0'
        );
    $rt_cnt_orig = $rt_cnt_orig->fetchAll(PDO::FETCH_KEY_PAIR);

    //いいね＆リツイートカウント数を配列に入れておく
    $cnt_f_retweet = $db->query(
        'SELECT 
            posts_id,
            SUM(rt) AS cnt_rt,
            SUM(ine) AS cnt_ine
         FROM 
            rt_ine
         GROUP BY 
            posts_id'
    );
    $cnt_f_retweet = $cnt_f_retweet->fetchAll(PDO::FETCH_ASSOC|PDO::FETCH_UNIQUE);

    //リツイート者をIDではなくNAMEで表示させるためにmembersを配列に入れておく
    $rt_id_name = $db->query('SELECT id, name FROM members');
    $rt_id_name = $rt_id_name->fetchAll(PDO::FETCH_KEY_PAIR);

//返信の場合！
if(isset($_REQUEST['res'])) {
    $response = $db->prepare('SELECT m.name, m.picture, p.* FROM members m, posts p WHERE m.id=p.member_id AND p.id=? ORDER BY p.created DESC');
    $response->execute(array($_REQUEST['res']));

    $table = $response->fetch();
    $message = '@' . $table['name'] . ' ' . $table['message'];
}

?>




<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>ひとこと掲示板</title>

    <link rel="stylesheet" href="./css/style.css" />
</head>

<body>
<div id="wrap">
    <div id="head">
    <h1>ひとこと掲示板</h1>
    </div>
    <div id="content">
    <div style="text-align: right"><a href="logout.php">ログアウト</a></div>
        <form action="" method="post">
        <dl>
            <dt><?php echo h($member['name']); ?>さん、メッセージをどうぞ！</dt>
            <dd>
                <textarea name="message" cols="50" rows="5"><?php if(isset($message)): echo h($message); endif; ?></textarea>
                <input type="hidden" name="reply_post_id" value="<?php if(isset($_REQUEST['res'])): echo h($_REQUEST['res']); else: echo h("none"); endif; ?>"/>
            </dd>
        </dl>
        <div>
            <input type="submit" value="投稿する" />
        </div>
        </form>

        <?php foreach ($posts as $post): ?>

            <div class="msg">
                <?php if($post['retweet_post_id']>0): echo '<p class="day">&#8811;' . h($rt_id_name[$post['retweet_member_id']]) . 'さんがリツイート</p>'; endif; //リツイートの時だけ表示 ?>

                <img src="member_picture/<?php echo h($post['picture']); ?>" width="48" height="48" alt="<?php echo h($post['name']); ?>" />
                <p><?php echo makeLink(h($post['message'])); ?><span class="name">（<?php echo h($post['name']); ?>）</span>

                <?php if(isset($rt_cnt_orig[$post['id']])): //リツイートポストの時 ?>
                    [<a href="index.php?res=<?php echo h($rt_cnt_orig[$post['id']]); ?>">Re</a>]</p>
                    <p class="day"><a href="view.php?id=<?php echo h($rt_cnt_orig[$post['id']]); ?>"><?php echo h($post['created']); ?></a>
                <?php else: //通常ポストの時?>
                    [<a href="index.php?res=<?php echo h($post['id']); ?>">Re</a>]</p>
                    <p class="day"><a href="view.php?id=<?php echo h($post['id']); ?>"><?php echo h($post['created']); ?></a>
                <?php endif; ?>

                <?php if($post['reply_post_id'] > 0): ?>
                        <a href="view.php?id=<?php echo h($post['reply_post_id']); ?>">返信元のメッセージ</a>
                <?php endif; ?>

                <?php if($_SESSION['id'] === $post['member_id']): ?>
                    <?php if(isset($rt_cnt_orig[$post['id']])): //リツイートポストの時 ?>
                        [<a href="delete.php?id=<?php echo h($rt_cnt_orig[$post['id']]); ?>" style="color:#f33;">削除</a>]
                    <?php else: //通常ポストの時?>
                        [<a href="delete.php?id=<?php echo h($post['id']); ?>" style="color:#f33;">削除</a>]
                    <?php endif; ?>
                <?php endif; ?>

                <!-- いいね・リツイートのボタン表示 -->
                <p class="like_rt">
                <?php if(isset($rt_cnt_orig[$post['id']])): //リツイートポストの時(ine) ?> 
                    <a href="rtiine.php?page=<?php echo h($page); ?>&ine=<?php echo h($rt_cnt_orig[$post['id']]); ?>" <?php if(isset($rows[$rt_cnt_orig[$post['id']]])): echo 'class="done_ine"'; endif; ?>>&hearts; <?php if(isset($cnt_f_retweet[$rt_cnt_orig[$post['id']]]['cnt_ine']) && $cnt_f_retweet[$rt_cnt_orig[$post['id']]]['cnt_ine']!=0): echo h($cnt_f_retweet[$rt_cnt_orig[$post['id']]]['cnt_ine']); endif; ?></a>　
                <?php else: //通常ポストの時 ?>
                    <a href="rtiine.php?page=<?php echo h($page); ?>&ine=<?php echo h($post['id']); ?>" <?php if(isset($rows[$post['id']])): echo 'class="done_ine"'; endif; ?>>&hearts; <?php if(isset($cnt_f_retweet[$post['id']]['cnt_ine']) &&$cnt_f_retweet[$post['id']]['cnt_ine']!=0): echo($cnt_f_retweet[$post['id']]['cnt_ine']); endif; ?></a>　
                <?php endif; ?>

                <?php if(isset($rt_cnt_orig[$post['id']])): //リツイートポストの時(rt) ?> 
                    <a href="rtiine.php?page=<?php echo h($page); ?>&rt=<?php echo h($rt_cnt_orig[$post['id']]); ?>" <?php if(isset($rt_rows[$rt_cnt_orig[$post['id']]])): echo 'class="done_rt"'; endif; ?>>Retweet <?php if(isset($cnt_f_retweet[$rt_cnt_orig[$post['id']]]['cnt_rt']) && $cnt_f_retweet[$rt_cnt_orig[$post['id']]]['cnt_rt']!=0): echo h($cnt_f_retweet[$rt_cnt_orig[$post['id']]]['cnt_rt']); endif; ?></a>
                <?php else: //通常ポストの時 ?>
                    <a href="rtiine.php?page=<?php echo h($page); ?>&rt=<?php echo h($post['id']); ?>" <?php if(isset($rt_rows[$post['id']])): echo 'class="done_rt"'; endif; ?>>Retweet <?php if(isset($cnt_f_retweet[$post['id']]['cnt_rt']) && $cnt_f_retweet[$post['id']]['cnt_rt']!=0): echo($cnt_f_retweet[$post['id']]['cnt_rt']); endif; ?></a>
                <?php endif; ?>
                </p>
            
            </p>
            </div>

        <?php endforeach; ?>

        <ul class="paging">

            <?php if($page > 1) { ?>
                <li><a href="index.php?page=<?php echo($page - 1); ?>"><?php echo($page - 1); ?>ページへ</a></li>
            <?php } else { ?>
                <li>1ページ</li>
            <?php } ?>
            <?php if($page < $maxPage) { ?>
                <li><a href="index.php?page=<?php echo($page + 1); ?>"><?php echo($page + 1); ?>ページへ</a></li>
            <?php } else { ?>
                <li><?php echo($page); ?>ページ</li>
            <?php } ?>

        </ul>

    </div>
</div>
</body>
</html>
