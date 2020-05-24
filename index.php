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

//投稿を記録する！
if(!empty($_POST)) {
    if(isset($_POST['message'])) {
        //reply_post_idがnullなら０を入れる(DBに登録できない為)
        if(is_null($_POST['reply_post_id']) OR isset($_POST['reply_post_id'])){ 
            $_POST['reply_post_id'] = 0;
        }
        $message = $db->prepare('INSERT INTO posts SET member_id=?, message=?, reply_post_id=?, created=NOW()');
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
$posts = $db->query('SELECT m.name, m.picture, p.* FROM members m, posts p WHERE m.id=p.member_id ORDER BY p.created DESC');

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
        
        </p>
        </div>

        <?php endforeach; ?>

    </div>
</div>
</body>
</html>
