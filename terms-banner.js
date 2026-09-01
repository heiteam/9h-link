/**
 * 9H Link - 使用条款 & 风险告知弹窗
 * 首次使用时弹出，用户同意后关闭（localStorage 记录）
 */
(function(){
  var KEY = '9h_terms_agreed';
  if (localStorage.getItem(KEY)) return;

  var overlay = document.createElement('div');
  overlay.id = 'terms-overlay';
  overlay.innerHTML = '<div class="terms-modal">' +
    '<div class="terms-header">⚠️ 使用须知</div>' +
    '<div class="terms-body">' +
      '<p><strong>使用本服务前，请阅读以下条款：</strong></p>' +
      '<ol>' +
        '<li>您缩短的链接必须符合<strong>中华人民共和国法律法规</strong></li>' +
        '<li>禁止用于赌博、色情、诈骗、钓鱼、恶意软件分发等<strong>违法活动</strong></li>' +
        '<li>禁止用于盗版资源分享、侵犯他人隐私、SEO 作弊等<strong>违规行为</strong></li>' +
        '<li>短链接隐藏真实地址，请确保链接来源<strong>安全可信</strong></li>' +
        '<li>违规链接将被<strong>立即永久封禁</strong>，严重者追究法律责任</li>' +
      '</ol>' +
      '<p style="font-size:12px;color:var(--text-3)">详细条款请查看 <a href="/terms" target="_blank" style="color:var(--primary)">完整使用条款</a></p>' +
    '</div>' +
    '<div class="terms-footer">' +
      '<button id="terms-close" class="btn btn-primary" style="padding:10px 32px">我已阅读并同意</button>' +
    '</div>' +
  '</div>';

  var style = document.createElement('style');
  style.textContent = '#terms-overlay{position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.6);backdrop-filter:blur(4px);z-index:99999;display:flex;align-items:center;justify-content:center;padding:16px}' +
    '.terms-modal{background:var(--card);border:1px solid var(--border);border-radius:16px;max-width:480px;width:100%;box-shadow:0 25px 60px rgba(0,0,0,.3);overflow:hidden}' +
    '.terms-header{padding:20px 24px 0;font-size:18px;font-weight:800;color:var(--text)}' +
    '.terms-body{padding:16px 24px;font-size:13px;color:var(--text-2);line-height:1.8}' +
    '.terms-body ol{margin:8px 0;padding-left:20px}' +
    '.terms-body li{margin:4px 0}' +
    '.terms-footer{padding:16px 24px 20px;text-align:center}';

  document.head.appendChild(style);
  document.body.appendChild(overlay);

  document.getElementById('terms-close').addEventListener('click', function(){
    localStorage.setItem(KEY, '1');
    overlay.style.opacity = '0';
    overlay.style.transition = 'opacity .2s';
    setTimeout(function(){ overlay.remove(); }, 200);
  });
})();
