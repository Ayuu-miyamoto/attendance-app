<?php

return [
    // 必須項目が未入力の場合
    'required' => ':attributeを入力してください',

    // メールアドレスの形式が正しくない場合
    'email' => ':attributeはメール形式で入力してください',

    // パスワードが8文字未満の場合
    'min' => [
        'string' => ':attributeは8文字以上で入力してください',
    ],

    // 確認用パスワードが一致しない場合
    'confirmed' => ':attributeと一致しません',

    // 項目名を指定された日本語に置き換える
    'attributes' => [
        'name' => 'お名前',
        'email' => 'メールアドレス',
        'password' => 'パスワード',
        'password_confirmation' => 'パスワード',
    ],
];