<?php
session_start();
require('../dbconnect.php');

if(!empty($_POST)) {
    //エラー項目の確認
    if ($_POST['name'] == '') {
        $error['name'] = 'blank';
    }
    if ($_POST['email'] == '') {
        $error['email'] = 'blank';
    }
    if ($_POST['password'] == '') {
        $error['password'] = 'blank';
    }
    if (strlen($_POST['password']) < 4) {
        $error['password'] = 'length';
    }
    //アップロードされたファイル名を代入する
    $fileName = $_FILES['image']['name'];
    //画像が指定されているか確認、指定されてれば拡張子のチェックをする
    $image_none = NULL; //画像がアップロードされない時用の変数、初期化しておく
    if (!empty($fileName)) {
        $ext = substr($fileName, -3);
        if ($ext != 'jpg' && $ext != 'gif'){
            $error['image'] = 'type';
        }
    //画像が指定されていなければ、noneを代入
    } else {
        $image_none = 'none.jpg';
    }

    //重複アカウント（メールアドレス）のチェック
    if(empty($error)) {
        $member = $db->prepare('SELECT COUNT(*) AS cnt FROM members WHERE email=?');
        $member->execute(array($_POST['email']));
        $record = $member->fetch();
        if($record['cnt'] > 0) {
            $error['email'] = 'duplicate';
        }
    }

    if (empty($error) && empty($image_none)) {
        //画像をアップロードする(画像名には、他との重複さけるためUPLOADした日時を付与）
        $image = date('YmdHis') . $_FILES['image']['name'];
        move_uploaded_file($_FILES['image']['tmp_name'], '../member_picture/' . $image);
    }


    //error配列が空か確認し、エラーがなければSESSIONに値を保存しcheck.phpへ移動
    if (empty($error) && empty($image_none)) {
        $_SESSION['join'] = $_POST;
        $_SESSION['join']['image'] = $image;
        header('Location: check.php');
        exit();
    } if (!empty($image_none)) {
        $_SESSION['join'] = $_POST;
        $_SESSION['join']['image'] = $image_none;
        header('Location: check.php');
        exit();
    }
}

//書き直し
if(isset($_REQUEST['action']) && $_REQUEST['action'] == 'rewrite') {
    $_POST = $_SESSION['join'];
    $error['rewrite'] = true;
}
?>


<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>ひとこと掲示板</title>

    <link rel="stylesheet" href="../css/style.css" />
</head>

<body>
<div id="wrap">
    <div id="head">
    <h1>会員登録</h1>
    </div>
    <div id="content">
        <p>次のフォームに必要事項をご記入ください。</p>
        <form action="" method="post" enctype="multipart/form-data">
        <dl>
            <dt>ニックネーム<span class="required">必須</span></dt>
            <dd>
                <input type="text" name="name" size="35" maxlength="255" value="<?php if(isset($_POST['name'])): echo htmlspecialchars($_POST['name'], ENT_QUOTES); endif; ?>" />
                <?php if (isset($error['name']) && $error['name'] == 'blank'): ?>
                    <p class="error">※　ニックネームを入力してください</p>
                <?php endif; ?>
            </dd>

            <dt>メールアドレス<span class="required">必須</span></dt>
            <dd>
                <input type="text" name="email" size="35" maxlength="255" value="<?php if(isset($_POST['email'])): echo htmlspecialchars($_POST['email'], ENT_QUOTES); endif; ?>" />
                <?php if(isset($error['email']) && $error['email'] == 'blank'): ?>
                    <p class="error">※　メールアドレスを入力してください</p>
                <?php endif; ?>

                <?php if(isset($error['email']) && $error['email'] == 'duplicate'): ?>
                    <p class="error">※　指定されたメールアドレスは既に登録されています</p>
                <?php endif; ?>
            </dd>

            <dt>パスワード<span class="required">必須</span></dt>
            <dd>
                <input type="password" name="password" size="10" maxlength="20" value="<?php if(isset($_POST['password'])): echo htmlspecialchars($_POST['password'], ENT_QUOTES); endif; ?>" />
                <?php if(isset($error['password']) && $error['password'] == 'blank'): ?>
                    <p class="error">※　パスワードを入力してください</p>
                <?php endif; ?>

                <?php if(isset($error['password']) && $error['password'] == 'length'): ?>
                    <p class="error">※　パスワードは４文字以上で入力してください</p>
                <?php endif; ?>
            </dd>

            <dt>写真など</dt>
            <dd>
                <input type="file" name="image" size="35" />
                <!-- ファイルが画像じゃない時のエラー文 -->
                <?php if(isset($error['image']) && $error['image'] == 'type'): ?>
                <p class="error">※　写真などは「.gif」または「.jpg」の画像を指定して下さい。
                <?php endif; ?>

                <!-- エラーで登録画面へ戻った時、画像を改めて指定しなければいけない為、メッセージで指定を促す -->
                <?php if(!empty($error)): ?>
                <p class="error">※　恐れ入りますが、画像を改めて指定して下さい。</p>
                <?php endif; ?>
            </dd>
        </dl>

        <div><input type="submit" value="入力内容を確認する" /></div>
        </form>
    </div>
</div>
</body>
</html>
