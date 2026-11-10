<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$eraProfessor = lb_user() !== null;

unset($_SESSION['usuario'], $_SESSION['aluno']);
session_regenerate_id(true);
$_SESSION['_csrf'] = bin2hex(random_bytes(32));

flash('success', 'Sessao encerrada com seguranca. Ate logo!');
redirect($eraProfessor ? lb_url('login.php') : lb_url('index.php'));
