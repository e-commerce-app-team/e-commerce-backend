<?php
$u = \App\Models\User::whereIn('role', ['vendor', 'wholesale'])->first();
if($u) {
    $u->balance = 500000;
    $u->save();
    echo 'Balance added: ' . $u->balance;
} else {
    echo 'No seller found';
}
