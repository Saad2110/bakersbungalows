<?php
/*
* Template Name: Payment Option Page
*/

if(isset($_GET['a'])){
echo $_GET['a'];

$_SESSION['payment']=$_GET['a'];
$_SESSION['title']=$_GET['title'];
$_SESSION['individualPrice']=$_GET['individualPrice'];
$_SESSION['total']=$_GET['total'];

}

if(isset($_GET['fname'])){
	echo $_SESSION['payment'];
$_SESSION['fname']=$_GET['fname'];
$_SESSION['lname']=$_GET['lname'];
$_SESSION['email']=$_GET['email'];
$_SESSION['address']=$_GET['address'];
$_SESSION['zip']=$_GET['zip'];
$_SESSION['city']=$_GET['city'];
$_SESSION['country']=$_GET['country'];
$_SESSION['company']=$_GET['company'];
$_SESSION['vat']=$_GET['vat'];
$_SESSION['note']=$_GET['note'];
$_SESSION['phone']=$_GET['phone'];

}

