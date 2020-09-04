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
function validate_one_digits_zero($value) {
    return preg_match('/\A[0]+\z/',$value);
}
//頭に0は弾く
function validate_head_value_zero($value) {
    return preg_match('/\A[0]+[0-9]+\z/',$value);
}
