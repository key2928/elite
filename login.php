<?php
require 'config.php';

$erro = '';

function redirecionarPainel($tipo) {
    if ($tipo === 'admin') { header('Location: admin.php'); exit; }
    if (in_array($tipo, ['treinador', 'professor', 'instrutor'])) { header('Location: treinador.php'); exit; }
    header('Location: index.php'); exit;
}

// Já logado na sessão
if (isset($_SESSION['usuario_tipo'])) {
    redirecionarPainel($_SESSION['usuario_tipo']);
}

// Cookie "manter conectado"
if (isset($_COOKIE['konex_user'])) {
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ? AND ativo = 1 LIMIT 1");
    $stmt->execute([(int)$_COOKIE['konex_user']]);
    $usuario = $stmt->fetch();
    if ($usuario) {
        $_SESSION['usuario_id']   = $usuario['id'];
        $_SESSION['usuario_nome'] = $usuario['nome'];
        $_SESSION['usuario_tipo'] = $usuario['tipo'];
        redirecionarPainel($usuario['tipo']);
    } else {
        setcookie('konex_user', '', time() - 3600, '/');
    }
}

// Formulário de login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['email'] ?? '');
    $senha = trim($_POST['senha'] ?? '');

    if ($login !== '' && $senha !== '') {
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ? AND ativo = 1 LIMIT 1");
        $stmt->execute([$login]);
        $usuario = $stmt->fetch();

        if ($usuario && password_verify($senha, $usuario['senha'])) {
            $_SESSION['usuario_id']   = $usuario['id'];
            $_SESSION['usuario_nome'] = $usuario['nome'];
            $_SESSION['usuario_tipo'] = $usuario['tipo'];

            if (!empty($_POST['lembrar'])) {
                setcookie('konex_user', $usuario['id'], time() + 86400 * 30, '/');
            }
            redirecionarPainel($usuario['tipo']);
        } else {
            $erro = 'Login ou senha incorretos!';
        }
    } else {
        $erro = 'Preencha todos os campos.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Elite Thai Girls</title>
    <meta name="description" content="Academia Elite Thai Girls — Muay Thai & Fitness">
    <meta name="theme-color" content="#d62bc5">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Elite Thai Girls">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="application-name" content="Elite Thai Girls">
    <link rel="manifest" href="manifest.json">
    <link rel="icon" type="image/svg+xml" href="icon.svg">
    <link rel="apple-touch-icon" href="icon.svg">
    <style>
        *{box-sizing:border-box;font-family:'Segoe UI',sans-serif}
        body{background:#0a0010;margin:0;display:flex;flex-direction:column;justify-content:center;align-items:center;min-height:100vh;min-height:100dvh;padding:20px;position:relative;overflow:hidden}
        #stars-canvas{position:fixed;top:0;left:0;width:100%;height:100%;z-index:0;pointer-events:none}
        .brand{text-align:center;margin-bottom:28px;position:relative;z-index:1}
        .brand-logo{width:80px;height:80px;margin:0 auto 12px;display:block;filter:drop-shadow(0 0 12px rgba(123,44,191,.6))}
        .brand-name{color:#fff;font-size:22px;font-weight:800;text-transform:uppercase;letter-spacing:2px;line-height:1.2}
        .brand-name span{color:#c084fc}
        .login-box{background:rgba(15,5,25,.85);backdrop-filter:blur(10px);padding:36px 30px;border-radius:20px;width:100%;max-width:400px;text-align:center;box-shadow:0 0 40px rgba(123,44,191,.3),0 0 80px rgba(123,44,191,.1);border:1px solid rgba(123,44,191,.25);position:relative;z-index:1}
        h1{color:#c084fc;font-size:24px;text-transform:uppercase;font-style:italic;margin-bottom:24px;letter-spacing:2px;text-shadow:0 0 20px rgba(192,132,252,.4)}
        .erro{background:#4a1525;color:#ffb3c6;padding:10px;border-radius:8px;margin-bottom:20px;font-weight:bold;border:1px solid #ff4d6d}
        input[type="text"],input[type="password"]{width:100%;padding:15px;border-radius:10px;border:1px solid rgba(123,44,191,.3);background:rgba(20,10,35,.8);color:#fff;margin-bottom:15px;font-size:16px;transition:border-color .3s}
        input[type="text"]:focus,input[type="password"]:focus{outline:none;border-color:#7b2cbf;box-shadow:0 0 10px rgba(123,44,191,.3)}
        ::placeholder{color:#666}
        .check{display:flex;align-items:center;margin-bottom:25px;color:#a0a0a0;font-size:14px}
        .check input{margin-right:10px;width:18px;height:18px;cursor:pointer;accent-color:#7b2cbf}
        .btn{width:100%;padding:15px;border:none;border-radius:10px;background:#6d28d9;color:#fff;font-size:16px;font-weight:bold;cursor:pointer;text-transform:uppercase;transition:all .3s;box-shadow:0 4px 20px rgba(109,40,217,.4)}
        .btn:hover{background:#7c3aed;box-shadow:0 6px 28px rgba(109,40,217,.6);transform:translateY(-1px)}
    </style>
</head>
<body>
<canvas id="stars-canvas"></canvas>
<div class="brand">
    <img src="icon.svg" alt="Elite Thai Girls" class="brand-logo">
    <div class="brand-name"><span>Elite Thai</span> Girls</div>
</div>
<div class="login-box">
    <?php if ($erro): ?><div class="erro"><?= e($erro) ?></div><?php endif; ?>
    <h1>ENTRAR</h1>
    <form method="POST">
        <input type="text" name="email" placeholder="Seu E-mail ou Login" required>
        <input type="password" name="senha" placeholder="••••••••" required>
        <div class="check">
            <input type="checkbox" name="lembrar" id="lembrar">
            <label for="lembrar">Manter conectado</label>
        </div>
        <button type="submit" class="btn">ENTRAR NO TREINO</button>
    </form>
</div>
<script>
// Space stars canvas
(function(){
    var canvas = document.getElementById('stars-canvas');
    var ctx = canvas.getContext('2d');
    var stars = [];
    var nebula = [];
    function resize(){
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;
    }
    function init(){
        resize();
        stars = [];
        nebula = [];
        for(var i=0;i<200;i++){
            stars.push({
                x: Math.random()*canvas.width,
                y: Math.random()*canvas.height,
                r: Math.random()*1.5+0.2,
                alpha: Math.random()*0.8+0.2,
                speed: Math.random()*0.3+0.05,
                twinkle: Math.random()*0.02+0.005,
                dir: Math.random()>0.5?1:-1
            });
        }
        for(var j=0;j<4;j++){
            nebula.push({
                x: Math.random()*canvas.width,
                y: Math.random()*canvas.height,
                r: 80+Math.random()*180,
                alpha: 0.03+Math.random()*0.05
            });
        }
    }
    function draw(){
        ctx.clearRect(0,0,canvas.width,canvas.height);
        // nebula blobs
        nebula.forEach(function(n){
            var g = ctx.createRadialGradient(n.x,n.y,0,n.x,n.y,n.r);
            g.addColorStop(0,'rgba(109,40,217,'+n.alpha+')');
            g.addColorStop(0.5,'rgba(76,10,130,'+n.alpha*0.5+')');
            g.addColorStop(1,'rgba(0,0,0,0)');
            ctx.beginPath();
            ctx.arc(n.x,n.y,n.r,0,Math.PI*2);
            ctx.fillStyle=g;
            ctx.fill();
        });
        // stars
        stars.forEach(function(s){
            s.alpha += s.twinkle * s.dir;
            if(s.alpha>1){s.alpha=1;s.dir=-1;}
            if(s.alpha<0.1){s.alpha=0.1;s.dir=1;}
            ctx.beginPath();
            ctx.arc(s.x,s.y,s.r,0,Math.PI*2);
            ctx.fillStyle='rgba(255,255,255,'+s.alpha+')';
            ctx.fill();
        });
        requestAnimationFrame(draw);
    }
    window.addEventListener('resize',function(){init();});
    init();
    draw();
})();
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/sw.js').catch(function(){});
}
</script>
</body>
</html>