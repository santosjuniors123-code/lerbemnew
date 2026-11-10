<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

unset($_SESSION['aluno']);
session_regenerate_id(true);

flash('info', 'Voce saiu da area do aluno. Pode entrar novamente quando quiser registrar uma leitura.');
redirect(lb_url('index.php'));
