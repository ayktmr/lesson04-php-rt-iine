<?php
session_start();
require('dbconnect.php');
require_once('functions.php');


//「リツイートいいね」パラメータ値のチェック
if(isset($_REQUEST['ine'])){
    $ine_ck = mb_convert_kana($_REQUEST['ine'], 'n', 'UTF-8');
    if(validateInput01($ine_ck) || validateInput02($ine_ck) || !ctype_digit($ine_ck)){
        echo "不正な値が入力されたので中断しました";
        exit();
    }
}
if(isset($_REQUEST['rt'])){
    $rt_ck = mb_convert_kana($_REQUEST['rt'], 'n', 'UTF-8');
    if(validateInput01($rt_ck) || validateInput02($rt_ck) || !ctype_digit($rt_ck)){
        echo "不正な値が入力されたので中断しました";
        exit();
    }
}
//ページ数のパラメータ値チェック
if(isset($_REQUEST['page'])){
    $page_ck = mb_convert_kana($_REQUEST['page'], 'n', 'UTF-8');
    if(validateInput01($page_ck) || validateInput02($page_ck) || !ctype_digit($page_ck)){
        echo "不正な値が入力されたので中断しました";
        exit();
    }
    $url = "index.php?page=" . $_REQUEST['page'];
}



//「いいね」ボタン押した時！
if(isset($_REQUEST['ine'])) {
    //ineされたposts_idに対し、ログイン者が過去にineしてるか確認
    $ines = $db->prepare('SELECT * FROM rt_ine WHERE member_id=? AND posts_id=?');
    $ines->execute(array($_SESSION['id'], $_REQUEST['ine']));
    $ine = $ines->fetch();
    //rt_ineした事がある
    if($ine){
        //現在のineの値を確認し、スイッチさせる
        if($ine['ine'] === "1") {
            $ine = $db->prepare('UPDATE rt_ine SET ine=0, created=NOW() WHERE member_id=? AND posts_id=?');
            $ine->execute(array(
                $_SESSION['id'],
                $_REQUEST['ine']
            ));
            //リロード時の再実行を防ぐ
            header("Location:".$url);
            exit();
        } else {
            $ine = $db->prepare('UPDATE rt_ine SET ine=1, created=NOW() WHERE member_id=? AND posts_id=?');
            $ine->execute(array(
                $_SESSION['id'],
                $_REQUEST['ine']
            ));
            //リロード時の再実行を防ぐ
            header("Location:".$url);
            exit();
        }
    //rt_ineした事がない
    } else {
        //rt_ineTABLEにレコード追加(ineにフラグたてる)
        $ines = $db->prepare('INSERT INTO rt_ine SET member_id=?, posts_id=?,rt=0,ine=1,created=NOW()');
        $ines->execute(array(
            $_SESSION['id'],
            $_REQUEST['ine']
        ));
    }
}

//「リツイート」ボタン押した時！
if(isset($_REQUEST['rt'])) {
    //リツイートされたposts_idに対し、ログイン者が過去にリツイートしてるか確認
    $rts = $db->prepare('SELECT * FROM rt_ine WHERE member_id=? AND posts_id=?');
    $rts->execute(array(
        $_SESSION['id'],
        $_REQUEST['rt']
    ));
    $rt = $rts->fetch();
    //rt_ineした事がある（現在のrtの値を確認し、スイッチさせる）---------------------------------------
    if($rt){
        //rtフラグが１の時（今リツイート中である）
        if($rt['rt'] === "1") {
            //リツイートを取り消す（rt_ineTABLE：rt=0にする）
            $rt = $db->prepare('UPDATE rt_ine SET rt=0, created=NOW() WHERE member_id=? AND posts_id=?');
            $rt->execute(array(
                $_SESSION['id'],
                $_REQUEST['rt']
            ));
            //デリート処理をする（postsTABLE：delete_flg=1にする）
            $rt = $db->prepare('UPDATE posts SET delete_flg=1, created=NOW() WHERE retweet_member_id=? AND retweet_post_id=?');
            $rt->execute(array(
                $_SESSION['id'],
                $_REQUEST['rt']
            ));
            //リロード時の再実行を防ぐ
            header("Location:".$url);
            exit();
        //rtフラグが０の時（今リツイートしていない）
        } else {
            //リツイートする（rt_ineTABLE：rt=1にする）
            $rt = $db->prepare('UPDATE rt_ine SET rt=1, created=NOW() WHERE member_id=? AND posts_id=?');
            $rt->execute(array(
                $_SESSION['id'],
                $_REQUEST['rt']
            ));
            //デリートを取り消す（postsTABLE：delete_flg=0にする）
            $rt = $db->prepare('UPDATE posts SET delete_flg=0, created=NOW() WHERE retweet_member_id=? AND retweet_post_id=?');
            $rt->execute(array(
                $_SESSION['id'],
                $_REQUEST['rt']
            ));
            //リロード時の再実行を防ぐ
            header("Location:".$url);
            exit();
        }
    //rt_ineした事がない--------------------------------------------------------------------
    } else {
        //rt_ineテーブルにレコード追加
        $rts = $db->prepare('INSERT INTO rt_ine SET member_id=?, posts_id=?,rt=1,ine=0,created=NOW()');
        $rts->execute(array(
            $_SESSION['id'],
            $_REQUEST['rt']
        ));
        //postsテーブルにリツイートしたレコードを複製、その際、retweet_idを付与する
        $rt_add_orig = $db->prepare('SELECT * FROM posts WHERE id=?');
        $rt_add_orig->execute(array($_REQUEST['rt']));
        $rt_add_orig = $rt_add_orig->fetch();

        $rt_add = $db->prepare(
            'INSERT INTO posts
             SET 
                message=?,
                member_id=?,
                reply_post_id=?,
                retweet_post_id=?,
                retweet_member_id=?,
                delete_flg=0,
                created=NOW()'
            );
        $rt_add->execute(array(
            $rt_add_orig['message'],
            $rt_add_orig['member_id'],
            $rt_add_orig['reply_post_id'],
            $_REQUEST['rt'],
            $_SESSION['id']
        ));
    }
}

header("Location:" . $url);
exit();
