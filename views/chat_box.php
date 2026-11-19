<?php
/** Fenêtre de discussion façon messagerie. Variables : $msgs (décorés), $last, $url, $name, $sub, $back, $admin, $avatar */
$emojis = ['😀','😂','🤣','😊','😍','😘','😉','😎','🙏','👍','👌','👏','🙌','💪','🔥','❤️','💙','💚','😢','😭','😅','🤔','😮','😡','🎉','✅','❌','⭐','📍','📞','🚗','🏠','🧹','💇‍♀️','💄','💅'];
?>
<?php $trOn = str_starts_with((string) $url, '/admin/'); ?>
<div class="wa" data-chat data-url="<?= e($url) ?>" data-last="<?= (int) $last ?>"<?= $trOn ? ' data-tr="1"' : '' ?>>
  <header class="wa-head">
    <a class="wa-back" href="<?= e($back) ?>" aria-label="Retour"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg></a>
    <span class="wa-av"><?php if (!empty($avatar)): ?><?= e($avatar) ?><?php else: ?><img src="/assets/img/logo.svg" alt="" width="40" height="40"><?php endif; ?></span>
    <div class="wa-who"><?php if (!empty($name_href)): ?><a class="wa-who-link" href="<?= e($name_href) ?>" title="Voir le profil"><b><?= e($name) ?> ›</b></a><?php else: ?><b><?= e($name) ?></b><?php endif; ?><small><?= e($sub) ?></small></div>
  </header>
  <div class="wa-log" data-chat-log>
    <?= $top ?? '' ?>
    <?php if (!empty($notice)): ?><div class="chat-pour"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 11v6M12 7.5v.5"/></svg><span><?= $notice ?></span></div><?php endif; ?>
    <?php if (!$msgs && empty($top)): ?><p class="wa-empty" data-chat-empty>👋 Bonjour ! Comment pouvons-nous vous aider ?</p><?php endif; ?>
    <?php foreach ($msgs as $m) echo chat_msg_html($m); ?>
  </div>
  <div class="wa-th" data-th hidden>
    <header class="wa-th-h"><button type="button" class="wa-back" data-th-x aria-label="Fermer"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg></button><div><b>Fil de discussion</b><small><?= e($name) ?></small></div></header>
    <div class="wa-th-log" data-th-log></div>
    <form class="wa-th-form" data-th-form><textarea rows="1" maxlength="1000" placeholder="Répondre dans le fil" aria-label="Répondre dans le fil"></textarea><button class="wa-main" aria-label="Envoyer">➤</button></form>
  </div>
  <?php if ($trOn): ?><div class="tr-menu" data-tr-menu hidden><small>Traduire mon message en :</small><?php foreach (['en' => '🇬🇧 Anglais', 'fr' => '🇫🇷 Français', 'es' => '🇪🇸 Espagnol', 'pt' => '🇵🇹 Portugais', 'ar' => '🇸🇦 Arabe', 'zh-CN' => '🇨🇳 Chinois'] as $lc => $ll): ?><button type="button" data-tr-to="<?= $lc ?>"><?= $ll ?></button><?php endforeach; ?></div><?php endif; ?>
  <div class="rx-pick" data-rx-pick hidden><?php foreach (REACTIONS as $r): ?><button type="button" data-pick="<?= $r ?>"><?= $r ?></button><?php endforeach; ?><button type="button" class="pk-act" data-pk-reply title="Répondre">↩</button><button type="button" class="pk-act" data-pk-thread title="Répondre dans un fil">🧵</button></div>
  <?php if (!empty($footnote)): ?><p class="wa-foot-note"><?= e($footnote) ?></p><?php endif; ?>
  <form class="wa-form" method="post" action="<?= e($url) ?>" data-chat-form>
    <?= csrf_field() ?>
    <input type="hidden" name="reply_to" value="" data-reply-id>
    <div class="wa-replybar" data-reply-bar hidden><div><b data-reply-who></b><span data-reply-text></span></div><button type="button" data-reply-x aria-label="Annuler">✕</button></div>
    <div class="wa-emojis" data-emoji-panel hidden><?php foreach ($emojis as $em): ?><button type="button" data-emoji="<?= $em ?>"><?= $em ?></button><?php endforeach; ?></div>
    <div class="wa-row">
      <div class="wa-pill">
        <button type="button" class="wa-ic" data-emoji-btn aria-label="Émojis">😊</button>
        <textarea name="body" rows="1" maxlength="1000" placeholder="Message" aria-label="Votre message"><?= e($draft ?? '') ?></textarea>
        <?php if ($trOn): ?><button type="button" class="wa-ic" data-tr-draft aria-label="Traduire mon message" title="Traduire mon message">🌐</button><?php endif; ?>
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
<link rel="stylesheet" href="/assets/css/chat.css?v=8">
<script src="/assets/js/chat.js?v=9" defer></script>
