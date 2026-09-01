// Linux.do 登录状态检查组件
(function(){
  var el=document.getElementById('user-area');
  if(!el)return;
  fetch('/auth/check.php',{credentials:'same-origin'})
    .then(function(r){return r.json();})
    .then(function(d){
      if(d.logged_in){
        var u=d.user||{};
        el.innerHTML='<a href="/login" class="login-btn"><img src="'+(u.avatar||'')+'" style="width:22px;height:22px;border-radius:50%;object-fit:cover" onerror="this.style.display=\'none\'">'+u.username+'</a>';
      }else{
        el.innerHTML='<a href="/login" class="login-btn">🔗 登录</a>';
      }
    }).catch(function(){
      el.innerHTML='<a href="/login" class="login-btn">🔗 登录</a>';
    });
})();
