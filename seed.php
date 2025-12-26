<?php
require 'db.php';

$seedUsers = [
    ['name'=>'Owner One','email'=>'owner@example.com','password'=>'ownerpass','role'=>'owner'],
    ['name'=>'Student One','email'=>'student@example.com','password'=>'studentpass','role'=>'student'],
];

foreach ($seedUsers as $u) {
    $stmt = $mysqli->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param('s', $u['email']);
    $stmt->execute(); $stmt->store_result();
    if ($stmt->num_rows) {
        echo "User {$u['email']} already exists\n";
        $stmt->close();
        continue;
    }
    $stmt->close();

    $hash = password_hash($u['password'], PASSWORD_DEFAULT);
    $ins = $mysqli->prepare("INSERT INTO users (name,email,password,role) VALUES (?,?,?,?)");
    $ins->bind_param('ssss', $u['name'], $u['email'], $hash, $u['role']);
    if ($ins->execute()) echo "Created user {$u['email']} with password '{$u['password']}'\n"; else echo "Failed to create {$u['email']}: {$ins->error}\n";
    $ins->close();
}

echo "Done.\n";