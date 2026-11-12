<?php
/**
 * S1QLBR — Fale conosco (https://s1qlbr.com/contato/contato.php)
 * GET  = mostra o formulário.
 * POST = recebe a mensagem (JSON) e envia por e-mail para o endereço em s1qlbr-config.php ('contact').
 * Não grava a mensagem em lugar nenhum: ela só vai para o seu e-mail.
 */
declare(strict_types=1);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');

    $out = function (int $code, array $data): void { http_response_code($code); echo json_encode($data, JSON_UNESCAPED_UNICODE); exit; };
    $len = fn(string $s): int => function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : strlen($s);
    $clean = fn($v): string => trim(str_replace(["\r", "\n", "\0"], ' ', (string) $v));   // sem quebra de linha = sem injeção de cabeçalho

    // configuração fora da public_html (mesma do api.php)
    $docRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? __DIR__) ?: __DIR__;
    $cfgPath = null;
    foreach ([dirname(__DIR__), dirname(__DIR__, 2), dirname(__DIR__, 3), dirname(__DIR__, 4)] as $d) {
        $f = $d . '/s1qlbr-config.php';
        if (is_file($f) && strpos((string) realpath($f), $docRoot . DIRECTORY_SEPARATOR) !== 0) { $cfgPath = $f; break; }
    }
    if ($cfgPath === null) $out(500, ['error' => 'config']);
    $CFG = require $cfgPath;
    $C = $CFG['contact'] ?? [];
    if (empty($C['to']) || strpos((string) $C['to'], 'SEU_') === 0) $out(500, ['error' => 'config']);

    // só aceita envio do próprio site
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin === '' || !in_array($origin, (array) ($CFG['allowed_origins'] ?? []), true)) $out(403, ['error' => 'origin']);

    $in = json_decode((string) file_get_contents('php://input', false, null, 0, 16384), true);
    if (!is_array($in)) $out(400, ['error' => 'bad']);

    // armadilhas para robôs: campo escondido preenchido ou envio rápido demais
    if (trim((string) ($in['site'] ?? '')) !== '' || (int) ($in['t'] ?? 0) < 3) $out(200, ['ok' => true]);

    $TIPOS = ['erro' => 'Erro', 'sugestao' => 'Sugestão', 'elogio' => 'Elogio'];
    $tipo  = (string) ($in['tipo'] ?? '');
    $nome  = $clean($in['nome'] ?? '');
    $email = $clean($in['email'] ?? '');
    $msg   = trim(str_replace("\0", '', (string) ($in['mensagem'] ?? '')));
    $lang  = in_array($in['lang'] ?? 'pt', ['pt', 'en', 'es', 'zh'], true) ? $in['lang'] : 'pt';

    if (!isset($TIPOS[$tipo])) $out(400, ['error' => 'tipo']);
    if ($len($msg) < 10) $out(400, ['error' => 'curta']);
    if ($len($msg) > 3000 || $len($nome) > 80 || $len($email) > 120) $out(413, ['error' => 'longa']);
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $out(400, ['error' => 'email']);

    // limite por pessoa (IP só como hash, igual ao api.php)
    $dir = $CFG['data_dir'] ?? (dirname($cfgPath) . '/s1qlbr-data');
    if (!is_dir($dir)) @mkdir($dir, 0700, true);
    $ip = hash_hmac('sha256', $_SERVER['REMOTE_ADDR'] ?? '0', (string) ($CFG['salt'] ?? 's1qlbr'));
    $hit = function (string $file, int $window, int $max): bool {
        $fp = @fopen($file, 'c+'); if (!$fp) return true;
        flock($fp, LOCK_EX);
        $now = time();
        $arr = array_values(array_filter(json_decode(stream_get_contents($fp) ?: '[]', true) ?: [], fn($t) => $t > $now - $window));
        $ok = count($arr) < $max;
        if ($ok) $arr[] = $now;
        ftruncate($fp, 0); rewind($fp); fwrite($fp, json_encode($arr)); fflush($fp);
        flock($fp, LOCK_UN); fclose($fp);
        return $ok;
    };
    if (!$hit("$dir/ct-h-$ip", 3600, (int) ($C['per_ip_hour'] ?? 5)) || !$hit("$dir/ct-d-$ip", 86400, (int) ($C['per_ip_day'] ?? 15))) $out(429, ['error' => 'limite']);

    // monta e envia o e-mail
    $from = $clean($C['from'] ?? ('contato@' . preg_replace('/^www\./', '', (string) parse_url($origin, PHP_URL_HOST))));
    $assunto = '[S1QLBR] ' . $TIPOS[$tipo] . ($nome !== '' ? ' — ' . $nome : '');
    $assunto = function_exists('mb_encode_mimeheader') ? mb_encode_mimeheader($assunto, 'UTF-8', 'B') : '=?UTF-8?B?' . base64_encode($assunto) . '?=';
    $corpo = "Tipo: {$TIPOS[$tipo]}\n"
        . 'Nome: ' . ($nome !== '' ? $nome : '(não informado)') . "\n"
        . 'E-mail: ' . ($email !== '' ? $email : '(não informado)') . "\n"
        . "Idioma da página: $lang\n"
        . 'Data: ' . gmdate('Y-m-d H:i') . " UTC\n"
        . "--------------------------------------------\n\n"
        . $msg . "\n";
    $headers = [
        'From: S1QLBR <' . $from . '>',
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        'X-Mailer: S1QLBR',
    ];
    if ($email !== '') $headers[] = 'Reply-To: ' . $email;

    $ok = @mail((string) $C['to'], $assunto, $corpo, implode("\r\n", $headers), '-f' . $from);
    if (!$ok) $out(502, ['error' => 'envio']);
    $out(200, ['ok' => true]);
}
?><!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<!-- Google Analytics 4 -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-29WJ5GL1VZ"></script>
<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','G-29WJ5GL1VZ');</script>
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>Fale conosco · S1QLBR</title>
<meta name="description" content="Fale com o S1QLBR: envie erros, sugestões e elogios sobre o gerador de queries.">
<link rel="canonical" href="https://s1qlbr.com/contato/contato.php">
<meta name="theme-color" content="#0B1F2A">
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%20viewBox%3D%220%200%20120%20120%22%3E%3Crect%20width%3D%22120%22%20height%3D%22120%22%20rx%3D%2224%22%20fill%3D%22%230B1F2A%22/%3E%3Cpath%20d%3D%22M28%2054%20A32%2011%200%200%200%2092%2054%20V72%20A32%2011%200%200%201%2028%2072%20Z%22%20fill%3D%22%23B6F23A%22/%3E%3Cellipse%20cx%3D%2260%22%20cy%3D%2234%22%20rx%3D%2232%22%20ry%3D%2211%22%20fill%3D%22none%22%20stroke%3D%22%23E6F1F5%22%20stroke-width%3D%2210%22/%3E%3Cpath%20d%3D%22M28%2034%20V88%20A32%2011%200%200%200%2092%2088%20V34%22%20fill%3D%22none%22%20stroke%3D%22%23E6F1F5%22%20stroke-width%3D%2210%22%20stroke-linejoin%3D%22round%22/%3E%3C/svg%3E">
<script>try{var t=localStorage.getItem("s1ql-tema");if(t==="dark"||t==="light")document.documentElement.setAttribute("data-theme",t);}catch(e){}</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500&family=Space+Grotesk:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="/s1.css">
<script src="/menu.js" defer></script>
</head>
<body>
<div class="wrap">
  <header>
    <div class="ttl">
      <a class="home" href="/" aria-label="S1QLBR">
        <svg class="logo" viewBox="0 0 120 120" aria-hidden="true"><rect width="120" height="120" rx="26" fill="#0B1F2A"/><path d="M24 51 A28 10 0 0 0 80 51 V70 A28 10 0 0 1 24 70 Z" fill="#B6F23A"/><ellipse cx="52" cy="32" rx="28" ry="10" fill="none" stroke="#E6F1F5" stroke-width="6"/><path d="M24 32 V88 A28 10 0 0 0 80 88 V32" fill="none" stroke="#E6F1F5" stroke-width="6" stroke-linejoin="round"/><path d="M24 51 A28 10 0 0 0 80 51 M24 70 A28 10 0 0 0 80 70" fill="none" stroke="#E6F1F5" stroke-width="6"/><circle cx="84" cy="84" r="16" fill="#0B1F2A" stroke="#B6F23A" stroke-width="7"/><path d="M95.5 95.5 L105 105" stroke="#B6F23A" stroke-width="8" stroke-linecap="round"/></svg>
        <span class="wm">S1<span class="ql">QL</span><span class="br">BR</span></span>
      </a>
      <span class="tag" data-i="tag">Gerador de queries para threat hunting</span>
    </div>
    <div class="tools"><select id="lang" class="langsel"></select></div>
  </header>

  <main>
    <section class="panel">
      <h1 data-i="h1">Fale conosco</h1>
      <p class="lead" data-i="lead">Achou um erro, tem uma ideia ou quer só dizer que a ferramenta ajudou? Escreva aqui. Quem lê é a pessoa que mantém o S1QLBR.</p>
    </section>

    <section class="panel">
      <form class="form" id="form" novalidate>
        <label class="fld"><span data-i="tipo">Sobre o que você quer falar?</span>
          <select id="tipo" required>
            <option value="" data-i="tipo0">Escolha uma opção</option>
            <option value="erro" data-i="tErro">Erro: algo não funcionou</option>
            <option value="sugestao" data-i="tSug">Sugestão: ideia ou melhoria</option>
            <option value="elogio" data-i="tElo">Elogio</option>
          </select>
        </label>
        <div class="duo">
          <label class="fld"><span data-i="nome">Nome (opcional)</span><input type="text" id="nome" maxlength="80" autocomplete="name"></label>
          <label class="fld"><span data-i="email">E-mail (opcional)</span><input type="email" id="email" maxlength="120" autocomplete="email"><small data-i="emailHint">Só se quiser resposta.</small></label>
        </div>
        <label class="fld"><span data-i="msg">Mensagem</span>
          <textarea id="mensagem" maxlength="3000" required data-ph="msgPh"></textarea>
        </label>
        <label class="hp" aria-hidden="true">Site <input type="text" id="site" tabindex="-1" autocomplete="off"></label>
        <p class="lead" style="margin:0;font-size:.82rem" data-i="priv">Não coloque dados de clientes, senhas ou chaves. A mensagem vai direto para o e-mail do projeto e não fica guardada no site.</p>
        <div class="row">
          <button class="primary" type="submit" id="enviar" data-i="send">Enviar mensagem</button>
          <span class="count" id="count">0/3000</span>
        </div>
        <div class="msg" id="status" role="status" hidden></div>
      </form>
    </section>
  </main>

  <footer>
    <span><b>S1QLBR</b> · s1qlbr.com · <span data-i="foot">gratuito e independente. Compatível com SentinelOne, sem vínculo com a empresa.</span></span>
    <a href="/" data-i="back">Voltar para o gerador</a>
  </footer>
</div>

<script>
(function(){
  var TX = {
    pt:{title:"Fale conosco · S1QLBR", tag:"Gerador de queries para threat hunting", h1:"Fale conosco",
      lead:"Achou um erro, tem uma ideia ou quer só dizer que a ferramenta ajudou? Escreva aqui. Quem lê é a pessoa que mantém o S1QLBR.",
      tipo:"Sobre o que você quer falar?", tipo0:"Escolha uma opção", tErro:"Erro: algo não funcionou", tSug:"Sugestão: ideia ou melhoria", tElo:"Elogio",
      nome:"Nome (opcional)", email:"E-mail (opcional)", emailHint:"Só se quiser resposta.", msg:"Mensagem",
      msgPh:"Conte o que aconteceu ou o que você gostaria de ver. Se for erro, diga o que digitou e qual motor usou.",
      priv:"Não coloque dados de clientes, senhas ou chaves. A mensagem vai direto para o e-mail do projeto e não fica guardada no site.",
      send:"Enviar mensagem", sending:"Enviando…", ok:"Mensagem enviada. Obrigado por ajudar a melhorar o S1QLBR!",
      eTipo:"Escolha o assunto da mensagem.", eCurta:"Escreva pelo menos 10 caracteres.", eEmail:"Confira o e-mail. Ou deixe em branco.",
      eLonga:"Mensagem longa demais (máximo de 3000 caracteres).", eLimite:"Você já enviou várias mensagens. Tente de novo mais tarde.",
      eEnvio:"O servidor não conseguiu enviar o e-mail agora. Tente de novo em instantes.", eConfig:"O formulário ainda não está configurado no servidor.",
      eRede:"Não consegui falar com o servidor. Verifique sua conexão.", eGeral:"Não foi possível enviar. Tente de novo.",
      foot:"gratuito e independente. Compatível com SentinelOne, sem vínculo com a empresa.", back:"Voltar para o gerador", idioma:"Idioma"},
    en:{title:"Contact us · S1QLBR", tag:"Query builder for threat hunting", h1:"Contact us",
      lead:"Found a bug, have an idea or just want to say the tool helped? Write here. The person who maintains S1QLBR reads every message.",
      tipo:"What is it about?", tipo0:"Choose an option", tErro:"Bug: something didn't work", tSug:"Suggestion: idea or improvement", tElo:"Compliment",
      nome:"Name (optional)", email:"Email (optional)", emailHint:"Only if you want a reply.", msg:"Message",
      msgPh:"Tell us what happened or what you'd like to see. For bugs, say what you typed and which engine you used.",
      priv:"Don't include customer data, passwords or keys. The message goes straight to the project's email and is not stored on the site.",
      send:"Send message", sending:"Sending…", ok:"Message sent. Thanks for helping improve S1QLBR!",
      eTipo:"Choose what the message is about.", eCurta:"Write at least 10 characters.", eEmail:"Check the email address, or leave it blank.",
      eLonga:"Message too long (3000 characters max).", eLimite:"You've sent several messages already. Try again later.",
      eEnvio:"The server couldn't send the email right now. Try again in a moment.", eConfig:"The form isn't configured on the server yet.",
      eRede:"Couldn't reach the server. Check your connection.", eGeral:"Couldn't send. Try again.",
      foot:"free and independent. Compatible with SentinelOne, not affiliated with the company.", back:"Back to the query builder", idioma:"Language"},
    es:{title:"Contáctanos · S1QLBR", tag:"Generador de queries para threat hunting", h1:"Contáctanos",
      lead:"¿Encontraste un error, tienes una idea o solo quieres decir que la herramienta te ayudó? Escribe aquí. Lo lee la persona que mantiene S1QLBR.",
      tipo:"¿Sobre qué quieres hablar?", tipo0:"Elige una opción", tErro:"Error: algo no funcionó", tSug:"Sugerencia: idea o mejora", tElo:"Elogio",
      nome:"Nombre (opcional)", email:"Correo (opcional)", emailHint:"Solo si quieres respuesta.", msg:"Mensaje",
      msgPh:"Cuenta qué pasó o qué te gustaría ver. Si es un error, di qué escribiste y qué motor usaste.",
      priv:"No incluyas datos de clientes, contraseñas ni claves. El mensaje va directo al correo del proyecto y no se guarda en el sitio.",
      send:"Enviar mensaje", sending:"Enviando…", ok:"Mensaje enviado. ¡Gracias por ayudar a mejorar S1QLBR!",
      eTipo:"Elige el asunto del mensaje.", eCurta:"Escribe al menos 10 caracteres.", eEmail:"Revisa el correo, o déjalo en blanco.",
      eLonga:"Mensaje demasiado largo (máximo 3000 caracteres).", eLimite:"Ya enviaste varios mensajes. Intenta más tarde.",
      eEnvio:"El servidor no pudo enviar el correo ahora. Intenta de nuevo en un momento.", eConfig:"El formulario aún no está configurado en el servidor.",
      eRede:"No pude conectar con el servidor. Revisa tu conexión.", eGeral:"No se pudo enviar. Intenta de nuevo.",
      foot:"gratuito e independiente. Compatible con SentinelOne, sin vínculo con la empresa.", back:"Volver al generador", idioma:"Idioma"},
    zh:{title:"联系我们 · S1QLBR", tag:"威胁狩猎查询生成器", h1:"联系我们",
      lead:"发现了问题、有好的想法，或者只是想说这个工具帮到了你？请在这里留言。维护 S1QLBR 的人会阅读每一条消息。",
      tipo:"你想反馈什么？", tipo0:"请选择", tErro:"问题：某些功能无法使用", tSug:"建议：想法或改进", tElo:"表扬",
      nome:"姓名（可选）", email:"邮箱（可选）", emailHint:"仅在需要回复时填写。", msg:"消息",
      msgPh:"请描述发生了什么或你希望看到的功能。如果是问题，请说明你输入的内容和使用的引擎。",
      priv:"请不要填写客户数据、密码或密钥。消息会直接发送到项目邮箱，不会保存在网站上。",
      send:"发送消息", sending:"正在发送…", ok:"消息已发送。感谢你帮助改进 S1QLBR！",
      eTipo:"请选择消息类型。", eCurta:"请至少输入 10 个字符。", eEmail:"请检查邮箱地址，或留空。",
      eLonga:"消息太长（最多 3000 个字符）。", eLimite:"你已发送多条消息，请稍后再试。",
      eEnvio:"服务器暂时无法发送邮件，请稍后再试。", eConfig:"服务器尚未配置此表单。",
      eRede:"无法连接服务器，请检查网络。", eGeral:"发送失败，请重试。",
      foot:"免费且独立。兼容 SentinelOne，与该公司无关联。", back:"返回查询生成器", idioma:"语言"}
  };
  var t = function(k){ return TX.pt[k]; }, L = "pt", aberto = Date.now();
  var $ = function(id){ return document.getElementById(id); };
  var form = $("form"), st = $("status"), btn = $("enviar"), msg = $("mensagem");
  var ERR = {tipo:"eTipo", curta:"eCurta", email:"eEmail", longa:"eLonga", limite:"eLimite", envio:"eEnvio", config:"eConfig"};

  function status(tipo, txt){ st.hidden = false; st.className = "msg " + tipo; st.textContent = txt; }
  msg.addEventListener("input", function(){ $("count").textContent = msg.value.length + "/3000"; });

  // pré-seleciona o assunto com ?tipo=erro|sugestao|elogio
  try{ var pt = new URLSearchParams(location.search).get("tipo"); if(pt && $("tipo").querySelector('option[value="'+pt+'"]')) $("tipo").value = pt; }catch(e){}

  form.addEventListener("submit", function(ev){
    ev.preventDefault();
    var d = { tipo:$("tipo").value, nome:$("nome").value.trim(), email:$("email").value.trim(), mensagem:msg.value.trim(),
              site:$("site").value, t:Math.round((Date.now()-aberto)/1000), lang:L };
    if(!d.tipo){ status("err", t("eTipo")); $("tipo").focus(); return; }
    if(d.mensagem.length < 10){ status("err", t("eCurta")); msg.focus(); return; }
    if(d.email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(d.email)){ status("err", t("eEmail")); $("email").focus(); return; }
    btn.disabled = true; btn.textContent = t("sending"); st.hidden = true;
    fetch(location.pathname, {method:"POST", headers:{"content-type":"application/json"}, body:JSON.stringify(d)})
      .then(function(r){ return r.json().catch(function(){ return {}; }).then(function(j){ return {r:r, j:j}; }); })
      .then(function(x){
        if(x.r.ok && x.j.ok){ form.reset(); $("count").textContent = "0/3000"; status("ok", t("ok")); aberto = Date.now(); }
        else status("err", t(ERR[x.j.error] || "eGeral"));
      })
      .catch(function(){ status("err", t("eRede")); })
      .then(function(){ btn.disabled = false; btn.textContent = t("send"); });
  });

  function pronto(){ if(window.S1Menu) S1Menu.idioma(TX, function(l, fn){ L = l; t = fn; }); }
  if(window.S1Menu) pronto(); else document.addEventListener("DOMContentLoaded", pronto);
})();
</script>
</body>
</html>
