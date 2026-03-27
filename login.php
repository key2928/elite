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
        body{background:#0b0710;margin:0;display:flex;flex-direction:column;justify-content:center;align-items:center;min-height:100vh;min-height:100dvh;padding:20px;position:relative;overflow:hidden}
        #stars-canvas{position:fixed;top:0;left:0;width:100%;height:100%;z-index:0;pointer-events:none}
        .brand{text-align:center;margin-bottom:28px;position:relative;z-index:1}
        .brand-logo{width:80px;height:80px;margin:0 auto 12px;display:block}
        .brand-name{color:#fff;font-size:22px;font-weight:800;text-transform:uppercase;letter-spacing:2px;line-height:1.2}
        .brand-name span{background:linear-gradient(90deg,#d62bc5,#7b2cbf);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
        .login-box{background:rgba(21,17,27,.92);padding:36px 30px;border-radius:20px;width:100%;max-width:400px;text-align:center;box-shadow:0 0 40px rgba(123,44,191,.25),0 0 80px rgba(214,43,197,.08);border:1px solid rgba(123,44,191,.25);position:relative;z-index:1}
        h1{color:#d62bc5;font-size:24px;text-transform:uppercase;font-style:italic;margin-bottom:24px;letter-spacing:2px}
        .erro{background:#4a1525;color:#ffb3c6;padding:10px;border-radius:8px;margin-bottom:20px;font-weight:bold;border:1px solid #ff4d6d}
        input[type="text"],input[type="password"]{width:100%;padding:15px;border-radius:10px;border:1px solid rgba(123,44,191,.3);background:#0e0a16;color:#fff;margin-bottom:15px;font-size:16px}
        input[type="text"]::placeholder,input[type="password"]::placeholder{color:#6a5580}
        input[type="text"]:focus,input[type="password"]:focus{outline:none;border-color:#7b2cbf;box-shadow:0 0 0 2px rgba(123,44,191,.2)}
        .check{display:flex;align-items:center;margin-bottom:25px;color:#a0a0a0;font-size:14px}
        .check input{margin-right:10px;width:18px;height:18px;cursor:pointer;accent-color:#7b2cbf}
        .btn{width:100%;padding:15px;border:none;border-radius:10px;background:linear-gradient(90deg,#d62bc5,#7b2cbf);color:#fff;font-size:16px;font-weight:bold;cursor:pointer;text-transform:uppercase;transition:opacity .3s,box-shadow .3s;box-shadow:0 4px 20px rgba(123,44,191,.4)}
        .btn:hover{opacity:.88;box-shadow:0 6px 28px rgba(214,43,197,.5)}
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
(function(){
    var c=document.getElementById('stars-canvas');
    var ctx=c.getContext('2d');
    var W,H,stars=[];
    function resize(){W=c.width=window.innerWidth;H=c.height=window.innerHeight;}
    resize();
    window.addEventListener('resize',resize);
    for(var i=0;i<220;i++){
        stars.push({
            x:Math.random(),y:Math.random(),
            r:Math.random()*1.5+.3,
            o:Math.random()*.8+.15,
            s:Math.random()*.0004+.00006,
            d:Math.random()<.5?1:-1,
            hue:Math.random()<.35?290:0
        });
    }
    var nebula=[
        {x:.15,y:.25,rx:.35,ry:.22,c:'rgba(80,0,140,'},
        {x:.82,y:.7,rx:.3,ry:.25,c:'rgba(120,0,200,'},
        {x:.5,y:.55,rx:.45,ry:.3,c:'rgba(60,0,100,'}
    ];
    function draw(){
        ctx.clearRect(0,0,W,H);
        ctx.fillStyle='#07030f';
        ctx.fillRect(0,0,W,H);
        nebula.forEach(function(n){
            var gx=ctx.createRadialGradient(n.x*W,n.y*H,0,n.x*W,n.y*H,n.rx*W);
            gx.addColorStop(0,n.c+'0.06)');
            gx.addColorStop(1,n.c+'0)');
            ctx.fillStyle=gx;
            ctx.beginPath();
            ctx.ellipse(n.x*W,n.y*H,n.rx*W,n.ry*H,0,0,Math.PI*2);
            ctx.fill();
        });
        stars.forEach(function(s){
            s.o+=s.s*s.d;
            if(s.o>0.95||s.o<0.1)s.d*=-1;
            ctx.beginPath();
            ctx.arc(s.x*W,s.y*H,s.r,0,Math.PI*2);
            if(s.hue){
                ctx.fillStyle='hsla('+s.hue+',70%,75%,'+s.o+')';
            }else{
                ctx.fillStyle='rgba(255,255,255,'+s.o+')';
            }
            ctx.fill();
        });
        requestAnimationFrame(draw);
    }
    draw();
})();
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/sw.js').catch(function(){});
}
</script>
</body>
</html>