<?php
session_start();
require('dbconnect.php');

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


//「いいね」ボタン押した時！
if(isset($_REQUEST['ine'])) {
    //ineされたposts_idに対し、ログイン者が過去にineしてるか確認
    $ines = $db->prepare('SELECT * FROM rt_ine WHERE posts_id=? AND member_id=?');
    $ines->execute(array($_REQUEST['ine'],$member['id']));
    $ine = $ines->fetch();
    //rt_ineした事がある
    if($ine){
        //現在のineの値を確認し、スイッチさせる
        if($ine['ine'] == 1) {
            $ine = $db->prepare('UPDATE rt_ine SET ine=0, created=NOW() WHERE member_id=? AND posts_id=?');
            $ine->execute(array(
                $member['id'],
                $_REQUEST['ine']
            ));
        } else {
            $ine = $db->prepare('UPDATE rt_ine SET ine=1, created=NOW() WHERE member_id=? AND posts_id=?');
            $ine->execute(array(
                $member['id'],
                $_REQUEST['ine']
            ));
        }
    //rt_ineした事がない
    } else {
        //レコード追加
        $ines = $db->prepare('INSERT INTO rt_ine SET member_id=?, posts_id=?,rt=0,ine=1,created=NOW()');
        $ines->execute(array(
            $member['id'],
            $_REQUEST['ine']
        ));
    }
}

//「リツイート」ボタン押した時！
if(isset($_REQUEST['rt'])) {
    //リツイートされたposts_idに対し、ログイン者が過去にrtしてるか確認
    $rts = $db->prepare('SELECT * FROM rt_ine WHERE posts_id=? AND member_id=?');
    $rts->execute(array($_REQUEST['rt'],$member['id']));
    $rt = $rts->fetch();
    //rt_ineした事がある
    if($rt){
        //現在のrtの値を確認し、スイッチさせる
        //・既にrtしてるpostなのでrtを取り消す
        if($rt['rt'] == 1) {
            $rt = $db->prepare('UPDATE rt_ine SET rt=0, created=NOW() WHERE member_id=? AND posts_id=?');
            $rt->execute(array(
                $member['id'],
                $_REQUEST['rt']
            ));
        //・まだrtしてないのでrtする
        } else {
            $rt = $db->prepare('UPDATE rt_ine SET rt=1, created=NOW() WHERE member_id=? AND posts_id=?');
            $rt->execute(array(
                $member['id'],
                $_REQUEST['rt']
            ));
        }
    //rt_ineした事がない
    } else {
        //rt_ineテーブルにレコード追加
        $rts = $db->prepare('INSERT INTO rt_ine SET member_id=?, posts_id=?,rt=1,ine=0,created=NOW()');
        $rts->execute(array(
            $member['id'],
            $_REQUEST['rt']
        ));
        //postsテーブルにリツイートしたレコード複製retweet_idを付与し追加
        $rt_copy = $db->prepare(
            'INSERT INTO posts(
                message,
                member_id,
                reply_post_id,
                retweet_post_id,
                delete_flg,
                created
            )
            SELECT 
                message,
                member_id,
                reply_post_id,
                id=?,
                delete_flg,
                NOW()
            FROM posts 
            WHERE id=?'
        );
        $rt_copy->execute(array(
            $_REQUEST['rt'],
            $_REQUEST['rt']
        ));
    }
}




//投稿を記録する！
if(!empty($_POST)) {
    if(isset($_POST['message'])) {
        //reply_post_idがnullなら０を入れる(DBに登録できない為)
        if(is_null($_POST['reply_post_id']) OR isset($_POST['reply_post_id'])){ 
            $_POST['reply_post_id'] = 0;
        }
        $message = $db->prepare('INSERT INTO posts SET member_id=?, message=?, reply_post_id=?,retweet=?, delete_flg=0, created=NOW()');
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
if(!isset($_REQUEST['page']) || $_REQUEST['page'] == ''){
    $page = 1;
} else {
    $page = $_REQUEST['page'];
}
$page = max($page, 1);

    //最終ページを取得する
    $counts = $db->query('SELECT COUNT(*) AS cnt FROM posts');
    $cnt = $counts->fetch();
    $maxPage = ceil($cnt['cnt'] / 5);
    $page = min($page, $maxPage);

    $start = ($page - 1) * 5;

    $posts = $db->prepare('SELECT m.name, m.picture, p.* FROM members m, posts p WHERE m.id=p.member_id ORDER BY p.created DESC LIMIT ?, 5');
    $posts->bindParam(1, $start, PDO::PARAM_INT);
    $posts->execute();

//投稿取得
//・・・・＆いいね＆リツイート数のカウント取得(３つのテーブルのリレーションP192参照)
$posts = $db->prepare(
    'SELECT 
        m.name,
        m.picture,
        p.id,
        p.message,
        p.member_id,
        p.reply_post_id,
        p.created,
        p.modified,
        SUM(r.rt) AS rt_count,
        SUM(r.ine) AS ine_count
     FROM 
        members m,
        posts p LEFT JOIN rt_ine r ON p.id=r.posts_id
     WHERE 
        m.id=p.member_id
     GROUP BY 
        p.id
     ORDER BY p.created
     DESC LIMIT ?, 5'
     );
$posts->bindParam(1, $start, PDO::PARAM_INT);
$posts->execute();


//ログイン者が「いいね」した投稿IDとポストIDを取得（自分がアクション済の色を変えるクラス指定に使用）
    $ine_posts = $db->prepare('SELECT posts_id, id FROM rt_ine WHERE ine=1 && member_id=?');
    $ine_posts->execute(array(
        $member['id']
    ));
        $rows = $ine_posts->fetchAll(PDO::FETCH_KEY_PAIR);

//ログイン者が「リツイート」した投稿IDとポストIDを取得（自分がアクション済の色を変えるクラス指定に使用）
    $rt_posts = $db->prepare('SELECT posts_id, id FROM rt_ine WHERE rt=1 && member_id=?');
    $rt_posts->execute(array(
        $member['id']
    ));
        $rt_rows = $rt_posts->fetchAll(PDO::FETCH_KEY_PAIR);

//リツイートの場合
// if(isset($_REQUEST['rt'])){
//     $response = $db->prepare('SELECT m.name, m.picture, p.* FROM members m, posts p WHERE m.id=p.member_id AND p.id=? ORDER BY p.created DESC');
//     $response->execute(array(
//         $_REQUEST['rt']
//     ));
//     header('Location: index.php?message=retweet');
//     exit();
// }



//返信の場合！
if(isset($_REQUEST['res'])) {
    $response = $db->prepare('SELECT m.name, m.picture, p.* FROM members m, posts p WHERE m.id=p.member_id AND p.id=? ORDER BY p.created DESC');
    $response->execute(array($_REQUEST['res']));

    $table = $response->fetch();
    $message = '@' . $table['name'] . ' ' . $table['message'];
}


// htmlspecialcharsのショートカット
function h($value) {
    return htmlspecialchars($value, ENT_QUOTES);
}

//本文内のURLにリンクを設定する
function makeLink($value) {
    return mb_ereg_replace("(https?)(://[[:alnum;]\+\$\;\?\.%,!#~*/:@&=_-]+)", '<A HREF="\1\2">\1\2</a>', $value);
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
                <input type="hidden" name="reply_post_id" value="<?php echo h($_REQUEST['res']); ?>" />
            </dd>
        </dl>
        <div>
            <input type="submit" value="投稿する" />
        </div>
        </form>

        <?php foreach ($posts as $post): ?>

            <div class="msg">
                <p class="day">〇〇さんがリツイート</p>
                <img src="member_picture/<?php echo h($post['picture']); ?>" width="48" height="48" alt="<?php echo h($post['name']); ?>" />
                <p><?php echo makeLink(h($post['message'])); ?><span class="name">（<?php echo h($post['name']); ?>）</span>
                [<a href="index.php?res=<?php echo h($post['id']); ?>">Re</a>]</p>
                <p class="day"><a href="view.php?id=<?php echo h($post['id']); ?>"><?php echo h($post['created']); ?></a>
            
                <?php if($post['reply_post_id'] > 0): ?>

                    <a href="view.php?id=<?php echo h($post['reply_post_id']); ?>">返信元のメッセージ</a>

                <?php endif; ?>

                <?php if($_SESSION['id'] == $post['member_id']): ?>

                    [<a href="delete.php?id=<?php echo h($post['id']); ?>" style="color:#f33;">削除</a>]

                <?php endif; ?>

                <p class="like_rt">
                    <a href="index.php?page=<?php echo($page); ?>&ine=<?php echo h($post['id']); ?>" <?php if(isset($rows[$post['id']])): echo 'class="done_ine"'; endif; ?>>&hearts; <?php if($post['ine_count']): echo($post['ine_count']); endif; ?></a>　<a href="index.php?page=<?php echo($page); ?>&rt=<?php echo h($post['id']); ?>" <?php if(isset($rt_rows[$post['id']])): echo 'class="done_rt"'; endif; ?>>Retweet <?php if($post['rt_count']): echo($post['rt_count']); endif; ?></a>
                </p>
            
            </p>
            </div>

        <?php endforeach; ?>

        <ul class="paging">

            <?php if($page > 1) { ?>
                <li><a href="index.php?page=<?php print($page - 1); ?>">前のページへ</a></li>
            <?php } else { ?>
                <li>前のページへ</li>
            <?php } ?>
            <?php if($page < $maxPage) { ?>
                <li><a href="index.php?page=<?php print($page + 1); ?>">次のページへ</a></li>
            <?php } else { ?>
                <li>次のページへ</li>
            <?php } ?>

        </ul>

    </div>
</div>
</body>
</html>
