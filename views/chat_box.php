<?php
/** Fenêtre de discussion façon messagerie. Variables : $msgs (décorés), $last, $url, $name, $sub, $back, $admin, $avatar */
$emojis = ['😀','😂','🤣','😊','😍','😘','😉','😎','🙏','👍','👌','👏','🙌','💪','🔥','❤️','💙','💚','😢','😭','😅','🤔','😮','😡','🎉','✅','❌','⭐','📍','📞','🚗','🏠','🧹','💇‍♀️','💄','💅'];
?>
<div class="wa" data-chat data-url="<?= e($url) ?>" data-last="<?= (int) $last ?>">
  <header class="wa-head">
    <a class="wa-back" href="<?= e($back) ?>" aria-label="Retour"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg></a>
    <span class="wa-av"><?php if (!empty($avatar)): ?><?= e($avatar) ?><?php else: ?><img src="/assets/img/logo.svg" alt="" width="40" height="40"><?php endif; ?></span>
    <div class="wa-who"><b><?= e($name) ?></b><small><?= e($sub) ?></small></div>
  </header>
  <div class="wa-log" data-chat-log>
    <?php if (!$msgs): ?><p class="wa-empty" data-chat-empty>👋 Bonjour ! Comment pouvons-nous vous aider ?</p><?php endif; ?>
    <?php foreach ($msgs as $m) echo chat_msg_html($m); ?>
  </div>
  <form class="wa-form" method="post" action="<?= e($url) ?>" data-chat-form>
    <?= csrf_field() ?>
    <input type="hidden" name="reply_to" value="" data-reply-id>
    <div class="wa-replybar" data-reply-bar hidden><div><b data-reply-who></b><span data-reply-text></span></div><button type="button" data-reply-x aria-label="Annuler">✕</button></div>
    <div class="wa-emojis" data-emoji-panel hidden><?php foreach ($emojis as $em): ?><button type="button" data-emoji="<?= $em ?>"><?= $em ?></button><?php endforeach; ?></div>
    <div class="wa-row">
      <div class="wa-pill">
        <button type="button" class="wa-ic" data-emoji-btn aria-label="Émojis">😊</button>
        <textarea name="body" rows="1" maxlength="1000" placeholder="Message" aria-label="Votre message"></textarea>
        <button type="button" class="wa-ic" data-chat-attach aria-label="Joindre un fichier">📎</button>
        <button type="button" class="wa-ic" data-chat-cam aria-label="Prendre une photo">📷</button>
      </div>
      <button type="button" class="wa-main" data-chat-main aria-label="Message vocal"><span data-main-mic>🎤</span><span data-main-send hidden>➤</span></button>
    </div>
    <input type="file" name="file" hidden data-chat-file accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt">
    <input type="file" hidden data-chat-camfile accept="image/*" capture="environment">
    <div class="wa-rec" data-chat-rec hidden><span class="rec-dot"></span> <b data-rec-time>0:00</b> <button type="button" data-rec-cancel>✕ Annuler</button><button type="button" class="wa-main" data-rec-send aria-label="Envoyer le vocal">➤</button></div>
  </form>
</div>
<link rel="stylesheet" href="/assets/css/chat.css?v=1">
<script src="/assets/js/chat.js?v=1" defer></script>
