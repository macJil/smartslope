<?php
require_once __DIR__ . '/bootstrap.php';
$password='example-only-password';
$hash=hash_password($password);
test_check(password_verify($password,$hash),'Password hash verifies matching password.');
test_check(!password_verify('wrong-password',$hash),'Password hash rejects nonmatching password.');
$_SESSION=[]; start_session();
$_SESSION['user_id']=42; $_SESSION['role']='user';
test_check(is_logged_in(),'Session user is recognized.');
test_check(!is_admin(),'Resident session is not admin.');
$_SESSION['role']='admin'; test_check(is_admin(),'Admin role is recognized.');
$_SESSION['csrf_token']=str_repeat('a',64); $_SERVER['REQUEST_METHOD']='POST'; $_POST=['csrf_token'=>str_repeat('a',64)];
test_check(valid_csrf(),'Matching POST CSRF token passes.');
$_POST['csrf_token']=str_repeat('b',64); test_check(!valid_csrf(),'Wrong CSRF token fails.');
$_SERVER['REQUEST_METHOD']='GET'; test_check(!valid_csrf(),'GET cannot satisfy POST CSRF validation.');
echo "$testChecks auth checks passed.\n";
