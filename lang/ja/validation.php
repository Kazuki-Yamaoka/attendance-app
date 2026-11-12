<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines contain the default error messages used by
    | the validator class. Some of these rules have multiple versions such
    | as the size rules. Feel free to tweak each of these messages here.
    |
    */

    'accepted' => ':attribute を承認してください。',
    'active_url' => ':attribute は、有効なURLではありません。',
    'after' => ':attribute は :date より後の日付を指定してください。',
    // ... 必要に応じて必要な分だけ、または全体を日本語に書き換える ...
    'required' => ':attribute は必須です。',
    'email' => ':attribute の形式が正しくありません。',
    'min' => [
        'string' => ':attribute は :min 文字以上で入力してください。',
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Here you may specify custom validation messages for attributes using the
    | convention "attribute.rule" to name the line. This makes it quick to
    | specify a directive to, be native.
    |
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | The following language lines are used to make our attribute placeholders
    | more readable with something more friendly such as "E-Mail Address"
    |
    */

    'attributes' => [
        'email' => 'メールアドレス',
        'password' => 'パスワード',
        'name' => '名前',
    ],

];
