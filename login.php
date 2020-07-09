<?php
session_start();
require('dbconnect.php');

//Cookieにログイン情報が保存された状態でアクセスしてきた場合、POSTに代入する
if(isset($_COOKIE['email'])) {
    $_POST['email'] = $_COOKIE['email'];
    $_POST['password'] = $_COOKIE['password'];
    $_POST['save'] = 'on'; //最後のログインから２週間保存する設定
}

if(!empty($_POST)) {
    //ログインの処理
    if(isset($_POST['email']) && isset($_POST['password'])) {
        $login = $db->prepare('SELECT * FROM members WHERE email=? AND password=?');
        $login->execute(array(
            $_POST['email'],
            sha1($_POST['password'])
        ));
        $member = $login->fetch();

        if($member) {
        //ログイン成功
        $_SESSION['id'] = $member['id'];
        $_SESSION['time'] = time();

            //loginに成功したのち、次回から自動的にログインするにチェックが入っていれば、その情報をCOOKIEに保存する
            if(isset($_POST['save']) && $_POST['save'] === 'on') {
                setcookie('email' . $_POST['email'], time()+60*60*24*14);
                setcookie('password', $_POST['password'], time()+60*60*24*14);
            }

        header('Location: index.php');
        exit();
        } else {
            $error['login'] = 'failed';
        }
    } else {
        $error['login'] = 'blank';
    }
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
    <h1>ログインする</h1>
    </div>
    <div id="content">
        <div id="lead">
            <p>メールアドレスとパスワードを記入してログインしてください。</p>
            <p>入会手続きがまだの方はこちらからどうぞ。</p>
            <p>&raquo;<a href="join/">入会手続きをする</a></p>
        </div>
        <form action="" method="post">
            <dl>
                <dt>メールアドレス</dt>
                <dd>
                    <input type="text" name="email" size="35" maxlength="255" value="<?php if(isset($_POST['email'])): echo htmlspecialchars($_POST['email'], ENT_QUOTES); endif; ?>" />
                    <?php if(isset($error['login']) && $error['login'] === 'blank'): ?>
                    <p class="error">※　メールアドレスとパスワードをご記入ください</p>
                    <?php endif; ?>

                    <?php if(isset($error['login']) && $error['login'] === 'failed'): ?>
                    <p class="error">※　ログインに失敗しました。正しくご記入下さい</p>
                    <?php endif; ?>
                </dd>
                <dt>パスワード</dt>
                <dd>
                    <input type="password" name="password" size="35" maxlength="255" value="<?php if(isset($_POST['password'])): echo htmlspecialchars($_POST['password'], ENT_QUOTES); endif; ?>" />
                </dd>
                <dt>ログイン情報の記録</dt>
                <dd>
                    <input id="save" type="checkbox" name="save" value="on" /><label for="save">次回からは自動的にログインする</label>
                </dd>
            </dl>
            <div><input type="submit" value="ログインする" /></div>
        </form>
    </div> <!-- end content -->
</div> <!-- end wrap -->
</body>
</html>
