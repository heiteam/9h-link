<?php
/**
 * YourLink 短链接安全审核模块 v2
 * 恶意域名黑名单 + 安全域名白名单 + URL 模式分析
 */

// 黑名单域名（赌博/色情/毒品/钓鱼/欺诈/恶意软件等）
function load_blacklist() {
    $static = [
        // === 赌博/博彩 ===
        'bet365.com', 'bet365.cn', '188bet.com', '188bet.cn', 'ibet789.com',
        'm88.com', 'mansion88.com', 'crowncasino.com', 'crown.com',
        'williamhill.com', 'bwin.com', 'pinnacle.com', 'sbobet.com',
        'dafabet.com', 'fun88.com', 'w88.com', 'sbotop.com', 'bk8.com',
        'betway.com', 'unibet.com', '12bet.com', 'sc88.com', 'ca88.com',
        'mark six.com', 'hkjc.com', 'cp52.com', 'cpdyj.com',
        'ag8.com', 'ag88.com', 'hg88.com', 'hg0088.com', 'hg8888.com',
        'js666.com', 'k8.com', 'ylg.com', 'sunbet.com', 'vwin.com',
        '1888.com', 'bet365affiliates.com', 'intertops.com', 'sportsbet.com',
        // === 色情/成人 ===
        'pornhub.com', 'xvideos.com', 'xnxx.com', 'xhamster.com',
        'youporn.com', 'redtube.com', 'tube8.com', 'spankbang.com',
        'hentai.tv', 'onlyfans.com', 'chaturbate.com', 'livejasmin.com',
        'stripchat.com', 'sex.com', 'avgle.com', '91porn.com',
        'javhd.com', 'javbus.com', 'missav.com', 'javlibrary.com',
        'e-hentai.org', 'nhentai.net', '8muses.com', 'motherless.com',
        'ero-video.com', 'tnaflix.com', 'efukt.com', 'keezmovies.com',
        'eporner.com', 'porntrex.com', 'porndoe.com', 'pornkai.com',
        // === 毒品/违禁药品 ===
        'darknetmarket.com', 'silkroad.com', 'alphabay.com', 'dreammarket.com',
        'silkroad darknet.com', 'blacksprut.com', 'mega-market.com', 'hydra.com',
        // === 钓鱼/诈骗/仿冒（知名品牌钓鱼）===
        'secure-google.com', 'apple-icloud.com', 'paypal-verify.com',
        'amazon-security.com', 'bank-of-china.net', 'icbc-verify.com',
        'alipay-safe.com', 'wechat-verify.com', 'qq-security.com',
        'taobao-auction.com', 'jd-cashback.com', 'douyin-red.com',
        'binance-airdrop.com', 'mexc-gift.com', 'okx-bonus.com',
        'coinbase-verify.com', 'metamask-airdrop.com', 'uniswap-claim.com',
        'google-login.com', 'facebook-login.com', 'amazon-pay.com',
        'paypal-security.com', 'apple-id.com', 'icloud-info.com',
        'bank-verify.com', 'alipay-check.com', 'wechat-pay.com',
        'steam-community.com', 'steam-gift.com', 'discord-gift.com',
        // === 已知恶意软件/木马分发 ===
        'torrentkitty.com', 'softpedia.download', 'crackz.org',
        'keygen-download.com', 'patch-now.com', 'cracks4download.com',
        'cracks4windows.com', 'getintopc.com', 'igg-games.com',
        'gamecopyworld.com', 'megaup.net', 'uploaded.net',
        'apkpure.com', 'apkmirror.com', 'modyolo.com', 'apkmod.cc',
        // === 仿冒/盗版 ===
        'replicashop.com', 'fakeshirts.com', 'counterfeitgoods.com',
        'luxuryfake.com', 'watches-replica.com', 'designerfakes.com',
        'replicabags.com', 'fakebrands.com', 'shopreplica.com',
        // === 其他违法/灰色 ===
        'privatemoney.com', 'loanshark.com', 'sexdating.com', 'militaryarmor.com',
        'realestate.com', 'moneytransfer.com', 'quickloan.com',
        'paydayloan.com', 'cashadvance.com', 'pirateproxy.com',
        'thepiratebay.org', '1337x.to', 'rarbg.com', 'yts.mx',
        // === 加密货币庞氏/空气币 ===
        'bitcoin-mixer.com', 'crypto-mixer.com', 'tumbler.io',
        'pump-dump.com', 'rugpull.com', 'ponzi-plan.com',
        'pocket-option.com', 'olymptrade.com', 'iqoption.com',
        'binaryoption.com', 'hyip.com', 'get-rich.com',
    ];

    $dynamic = [];
    $extFile = __DIR__ . '/data/blacklist.json';
    if (file_exists($extFile)) {
        $ext = json_decode(@file_get_contents($extFile), true);
        if (is_array($ext)) {
            foreach ($ext as $d) {
                if (is_string($d) && $d !== '') $dynamic[] = strtolower(trim($d));
            }
        }
    }
    return array_values(array_unique(array_merge($static, $dynamic)));
}

// 安全域名白名单（自动通过，无需人工审核）
function safe_domains() {
    return [
        // 搜索引擎/门户
        'google.com', 'google.com.hk', 'google.co.jp', 'google.co.uk',
        'bing.com', 'baidu.com', 'yandex.com', 'duckduckgo.com',
        'yahoo.com', 'yahoo.co.jp',
        // 社交媒体
        'facebook.com', 'instagram.com', 'twitter.com', 'x.com',
        'linkedin.com', 'tiktok.com', 'snapchat.com', 'pinterest.com',
        'reddit.com', 'telegram.org', 'discord.com', 'whatsapp.com',
        'weibo.com', 'weixin.qq.com', 'qq.com', 'qzone.qq.com',
        // 视频/内容
        'youtube.com', 'youtu.be', 'bilibili.com', 'netflix.com',
        'spotify.com', 'twitch.tv', 'vimeo.com', 'dailymotion.com',
        // 新闻/媒体
        'bbc.com', 'bbc.co.uk', 'cnn.com', 'nytimes.com', 'reuters.com',
        'ap.org', 'npr.org', 'theguardian.com', 'wsj.com', 'bloomberg.com',
        'ft.com', 'economist.com', 'washingtonpost.com', 'nbcnews.com',
        'abcnews.com', 'cbsnews.com', 'foxnews.com', 'usatoday.com',
        'theverge.com', 'wired.com', 'techcrunch.com', 'arstechnica.com',
        'zhihu.com', 'huxiu.com', '36kr.com', 'thepaper.cn',
        'xinhuanet.com', 'people.com.cn', 'chinadaily.com.cn',
        'cctv.com', 'sina.com.cn', 'sohu.com', '163.com',
        // 电商
        'amazon.com', 'amazon.cn', 'amazon.co.jp', 'amazon.co.uk',
        'taobao.com', 'tmall.com', 'jd.com', 'pinduoduo.com',
        'ebay.com', 'walmart.com', 'bestbuy.com', 'target.com',
        'shopify.com', 'etsy.com', 'rakuten.co.jp', 'mercari.com',
        // 科技/开源
        'github.com', 'gitlab.com', 'bitbucket.org', 'stackoverflow.com',
        'stackexchange.com', 'npmjs.com', 'pypi.org', 'docker.com',
        'microsoft.com', 'apple.com', 'google.dev', 'developers.google.com',
        // 教育/政府
        'edu.cn', 'edu.hk', 'edu.tw', 'gov.cn', 'gov.hk',
        'wikipedia.org', 'wikimedia.org', 'wikihow.com',
        'coursera.org', 'edx.org', 'udemy.com', 'khanacademy.org',
        // 域名注册商/云服务（防止被滥用，但基本可信）
        'godaddy.com', 'namecheap.com', 'cloudflare.com', 'akamai.com',
        'aliyun.com', 'tencent.com', 'huaweicloud.com', 'qcloud.com',
        'aws.amazon.com', 'azure.microsoft.com', 'vercel.com', 'netlify.com',
        // 支付/金融
        'paypal.com', 'stripe.com', 'alipay.com', 'wechatpay.com',
        'square.com', 'revolut.com', 'wise.com',
        // 地图/出行
        'maps.google.com', 'map.baidu.com', 'amap.com',
        'didiglobal.com', 'uber.com', 'lyft.com', 'airbnb.com',
        'booking.com', 'tripadvisor.com', 'ctrip.com',
        // YourLink 自身
        'your-domain.com', 'initjj.com',
    ];
}

// 可疑 TLD（免费注册，钓鱼常用）
function suspicious_tlds() {
    return ['.tk', '.ml', '.ga', '.cf', '.gq', '.xyz', '.top', '.icu', '.buzz', '.club', '.online', '.site', '.win', '.bid', '.loan', '.download', '.men', '.party', '.date', '.science', '.click', '.link', '.work', '.lol', '.mom', '.mom', '.gdn', '.jet'];
}

/**
 * URL 安全分析 v2
 * 返回: ['ok'=>bool, 'reason'=>string, 'level'=>'block'|'review'|'pass'|'auto_approve']
 */
function audit_url($url) {
    $parts = @parse_url($url);
    if (!$parts || empty($parts['host'])) {
        return ['ok' => false, 'reason' => '链接格式不合法', 'level' => 'block'];
    }
    $scheme = strtolower($parts['scheme'] ?? '');
    $host = strtolower($parts['host'] ?? '');
    $full = strtolower($url);
    $path = $parts['path'] ?? '/';

    // 基础检查
    if (!in_array($scheme, ['http', 'https'], true)) {
        return ['ok' => false, 'reason' => '仅支持 http/https 链接', 'level' => 'block'];
    }
    // IP 地址直连
    if (filter_var($host, FILTER_VALIDATE_IP)) {
        return ['ok' => false, 'reason' => '不支持 IP 直连链接，请使用域名', 'level' => 'block'];
    }
    // 内网地址
    $private_pattern = '/^(10\.|172\.(1[6-9]|2[0-9]|3[01])\.|192\.168\.|127\.|0\.)/';
    if (preg_match($private_pattern, $host)) {
        return ['ok' => false, 'reason' => '不支持内网地址链接', 'level' => 'block'];
    }
    if (str_ends_with($host, '.local') || str_ends_with($host, '.internal') || str_ends_with($host, '.lan')) {
        return ['ok' => false, 'reason' => '不支持内网地址链接', 'level' => 'block'];
    }

    // 安全域名白名单检查（自动通过）
    $safe_list = safe_domains();
    foreach ($safe_list as $safe) {
        $safe = strtolower(trim($safe));
        if ($host === $safe || str_ends_with($host, '.' . $safe)) {
            return ['ok' => true, 'reason' => '安全域名，自动放行', 'level' => 'auto_approve'];
        }
    }

    // 黑名单检查
    $blacklist = load_blacklist();
    foreach ($blacklist as $bad) {
        $bad = strtolower(trim($bad));
        if ($bad === '') continue;
        if ($host === $bad || str_ends_with($host, '.' . $bad)) {
            return ['ok' => false, 'reason' => '目标链接包含违禁内容域名（' . $bad . '）', 'level' => 'block'];
        }
        if (strpos($full, $bad) !== false) {
            return ['ok' => false, 'reason' => '目标链接包含违禁关键词', 'level' => 'block'];
        }
    }

    // 可疑 TLD 检查
    $tlds = suspicious_tlds();
    foreach ($tlds as $tld) {
        if (str_ends_with($host, $tld)) {
            return ['ok' => true, 'reason' => '可疑免费域名（' . $tld . '），需人工审核', 'level' => 'review'];
        }
    }

    // 短链接嵌套检测（指向另一个短链接服务）
    $shortener_domains = ['bit.ly', 'tinyurl.com', 'goo.gl', 'ow.ly', 'is.gd', 'buff.ly',
        'cutt.ly', 't.co', 'shorturl.at', 'rb.gy', 'short.link', 'clck.ru',
        'tiny.cc', 'bl.ink', 's.id', 'adf.ly', 'shorte.st', 'bc.vc',
        'u.to', 'c1n.cn', 'suowo.cn', 'suo.im', 'dwz.cn', 'url.cn',
        'your-domain.com'];
    foreach ($shortener_domains as $sd) {
        if ($host === $sd || str_ends_with($host, '.' . $sd)) {
            return ['ok' => true, 'reason' => '指向短链接服务，需人工审核', 'level' => 'review'];
        }
    }

    // 高风险关键词检查（触发人工审核）
    $risk_keywords = [
        'gambl', 'casino', 'betting', 'lottery', 'poker', 'slots',
        'porn', 'xxx', 'adult', 'escort', 'dating', 'swinger',
        'crack', 'keygen', 'patch', 'warez', 'torrent', 'pirate',
        'pharmacy', 'viagra', 'cialis', 'xanax', 'valium', 'adderall',
        'bitcoin', 'crypto', 'airdrop', 'bonus', 'free-money',
        'loan', 'payday', 'loanapp', 'quick-cash', 'easy-money',
        'replica', 'fake', 'counterfeit', 'knockoff',
        'hack', 'cheat', 'exploit', 'free-robux', 'free-vbucks',
        'survey', 'win-a', 'claim', 'prize', 'congratulations',
        'investment', 'trading-signal', 'forex', 'binary-option',
    ];
    foreach ($risk_keywords as $kw) {
        if (strpos($full, $kw) !== false) {
            return ['ok' => true, 'reason' => '包含高风险关键词：' . $kw . '，需人工审核', 'level' => 'review'];
        }
    }

    // 纯数字/乱码域名检查（钓鱼常用）
    $host_without_tld = implode('.', array_slice(explode('.', $host), 0, -1));
    if (preg_match('/^[0-9]{5,}$/', $host_without_tld)) {
        return ['ok' => true, 'reason' => '域名疑似自动生成，需人工审核', 'level' => 'review'];
    }
    // 域名包含连字符过多（钓鱼仿冒站点特征）
    if (substr_count($host, '-') >= 3) {
        return ['ok' => true, 'reason' => '域名含多个连字符，需人工审核', 'level' => 'review'];
    }

    // 路径包含可疑模式
    $suspicious_paths = ['/login', '/verify', '/secure', '/account', '/wallet',
        '/password', '/reset', '/recover', '/authentication',
        '/download/', '/setup.exe', '/setup.msi', '.apk', '.bat', '.vbs',
        '/free/', '/bonus/', '/promo/', '/gift/', '/claim/'];
    foreach ($suspicious_paths as $sp) {
        if (strpos($path, $sp) !== false) {
            return ['ok' => true, 'reason' => '路径含敏感模式：' . $sp . '，需人工审核', 'level' => 'review'];
        }
    }

    return ['ok' => true, 'reason' => '自动检查通过', 'level' => 'pass'];
}