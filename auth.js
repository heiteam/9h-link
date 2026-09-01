/* YourLink 登录组件 - 醒目按钮 + 登录后显示用户名 */
document.addEventListener('DOMContentLoaded', function(){
  // 注入CSS
  var css = document.createElement('style');
  css.textContent = '.h-login{position:fixed;top:14px;right:18px;z-index:9999}.h-login .login-btn{display:inline-flex;align-items:center;gap:6px;padding:8px 18px;background:linear-gradient(135deg,#667eea,#764ba2);border:none;border-radius:20px;font-size:13px;font-weight:700;color:#fff;text-decoration:none;transition:all .2s;cursor:pointer;box-shadow:0 4px 12px rgba(102,126,234,.35)}.h-login .login-btn:hover{transform:translateY(-1px);box-shadow:0 6px 18px rgba(102,126,234,.5)}.h-login .user-info{display:inline-flex;align-items:center;gap:8px;padding:5px 12px 5px 6px;background:rgba(255,255,255,1);border:1px solid rgba(255,255,255,.5);border-radius:22px;font-size:13px;color:#111827;backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px)}.h-login .user-info img{width:28px;height:28px;border-radius:50%;border:2px solid #667eea}.h-login .user-info .uname{color:#111827;font-weight:700;max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.h-login .user-info .logout-btn{color:#dc2626;text-decoration:none;font-size:12px;font-weight:600;margin-left:8px}.h-login .user-info .profile-link{display:inline-flex;align-items:center;gap:8px;text-decoration:none;color:inherit}.h-login .user-info a:hover{text-decoration:underline}';
  document.head.appendChild(css);

  var el = document.createElement('div');
  el.className = 'h-login';
  el.id = 'h-login';
  document.body.appendChild(el);

  fetch('/auth/check.php', {credentials: 'same-origin'})
    .then(function(r){ return r.json(); })
    .then(function(d){
      if(d.logged_in && d.user){
        var name = d.user.name || d.user.username || '用户';
        var avatar = d.user.avatar ? '<img src="'+d.user.avatar+'" alt="" onerror="this.style.display=\'none\'">' : '';
        el.innerHTML = '<div class="user-info"><a href="/profile" class="profile-link">'+avatar+'<span class="uname">'+name+'</span></a><a href="/auth/logout.php" class="logout-btn">退出</a></div>';
      } else {
        el.innerHTML = '<a href="/login" class="login-btn">🔗 Linux.do 登录</a>';
      }
    }).catch(function(){
      el.innerHTML = '<a href="/login" class="login-btn">🔗 Linux.do 登录</a>';
    });
});
