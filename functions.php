<?php

// htmlspecialcharsのショートカット
function h($value) {
    return htmlspecialchars($value, ENT_QUOTES);
}

//本文内のURLにリンクを設定する
function makeLink($value) {
    return mb_ereg_replace("(https?)(://[[:alnum;]\+\$\;\?\.%,!#~*/:@&=_-]+)", '<A HREF="\1\2">\1\2</a>', $value);
}

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
