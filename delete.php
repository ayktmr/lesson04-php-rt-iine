<?php
session_start();
require('dbconnect.php');

//0のみは弾く
function v1($value) {
    return preg_match('/\A[0]+\z/',$value);
}
//頭に0は弾く
function v2($value) {
    return preg_match('/\A[0]+[0-9]+\z/',$value);
}
//3桁以上は弾く
function v3($value) {
    return preg_match('/\A[1-9][0-9]{3,}\z/',$value);
}

//(res(返信))のパラメータ値チェック
if(isset($_REQUEST['id'])){
    $id_ck = mb_convert_kana($_REQUEST['id'], 'n', 'UTF-8');
    if(v1($id_ck) || v2($id_ck) || v3($id_ck) || !ctype_digit($id_ck)){
        echo "不正な値が入力されたので中断しました";
        exit();
    }
}


if(isset($_SESSION['id'])) {
    $id = $_REQUEST['id'];

    //投稿を検査する
    $message = $db->prepare('SELECT * FROM posts WHERE id=?');
    $message->execute(array($id));
    $message = $message->fetch();

    if($message['member_id'] === $_SESSION['id']) {
        //デリート処理をする（postsTABLE：delete_flg=1にする）
        $del = $db->prepare('UPDATE posts SET delete_flg=1, created=NOW() WHERE id=? OR retweet_post_id=?');
        $del->execute(array(
            $_REQUEST['id'],
            $_REQUEST['id']
        ));
    }


}

header('Location: index.php');
exit();
