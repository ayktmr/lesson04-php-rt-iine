<?php
session_start();
require('dbconnect.php');

if(isset($_SESSION['id'])) {
    $id = $_REQUEST['id'];

    //投稿を検査する
    $message = $db->prepare('SELECT * FROM posts WHERE id=?');
    $message->execute(array($id));
    $message = $message->fetch();

    if($message['member_id'] == $_SESSION['id']) {
        //削除する！
        // $del = $db->prepare('DELETE FROM posts WHERE id=?');
        // $del->execute(array($id));

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

?>