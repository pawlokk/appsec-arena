<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/include/session.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/include/mysql_connect.inc.php');

$cookie_login = isset($_COOKIE['ATLogin']) ? $_COOKIE['ATLogin'] : null;
$cookie_pass  = isset($_COOKIE['ATPass'])  ? $_COOKIE['ATPass']  : null;

if ($cookie_login && $cookie_pass && !isset($_POST['submit'])) {
    $safe_login = $addslashes($cookie_login);
    $member_row = queryDB(
        "SELECT password, last_login FROM AT_members WHERE login='%s'",
        array($safe_login),
        true
    );
    if ($member_row) {
        $salted = hash('sha512', $member_row['password'] . hash('sha512', $member_row['last_login']));
        if (hash_equals($salted, $cookie_pass)) {
            $row = queryDB(
                "SELECT member_id, login FROM AT_members WHERE login='%s'",
                array($safe_login),
                true
            );
            if ($row) {
                $_SESSION['valid_user'] = true;
                $_SESSION['member_id']  = intval($row['member_id']);
                $_SESSION['login']      = $row['login'];
                header('Location: /dashboard.php');
                exit;
            }
        }
    }
}

if (isset($_POST['token'])) {
    $_SESSION['token'] = $_POST['token'];
} else {
    if (!isset($_SESSION['token'])) {
        $_SESSION['token'] = sha1(mt_rand() . microtime(true));
    }
}

$error = '';

if (isset($_POST['submit'])) {
    $this_login    = $_POST['form_login'];
    $this_password = $_POST['form_password_hidden'];

    $this_login    = $addslashes($this_login);
    $this_password = $addslashes($this_password);

    $rows = queryDB(
        "SELECT login, attempt, expiry FROM AT_member_login_attempt WHERE login='%s'",
        array($this_login),
        true
    );

    $row = queryDB(
        "SELECT member_id, login, first_name, last_name, preferences, language, status, password AS pass, last_login FROM AT_members WHERE (login='%s' OR email='%s') AND SHA1(CONCAT(password, '%s'))='%s'",
        array($this_login, $this_login, $_SESSION['token'], $this_password),
        true
    );

    if ($row && count($row) > 0) {
        $_SESSION['valid_user']  = true;
        $_SESSION['member_id']   = intval($row['member_id']);
        $_SESSION['login']       = $row['login'];
        header('Location: /dashboard.php');
        exit;
    } else {
        $error = 'Invalid login or password.';
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Academy - Login</title>
<script>
function encrypt_password() {
    var pw = document.querySelector('[name=form_password]').value;
    var token = '<?php echo htmlspecialchars($_SESSION['token']); ?>';
    document.querySelector('[name=form_password_hidden]').value = sha1(sha1(pw) + token);
    document.querySelector('[name=form_password]').value = '';
    return true;
}

function sha1(str) {
    function rotl(n,s){return n<<s|n>>>32-s}
    function tohex(i){var s='';for(var j=7;j>=0;j--){s+=((i>>>(j*4))&0x0f).toString(16)}return s}
    str=unescape(encodeURIComponent(str));
    var msg=[];for(var i=0;i<str.length;i++){msg.push(str.charCodeAt(i))}
    var l=msg.length;msg.push(0x80);
    while(msg.length%64!==56)msg.push(0);
    var hi=Math.floor(l*8/0x100000000),lo=l*8>>>0;
    msg.push(hi>>>24&0xff,hi>>>16&0xff,hi>>>8&0xff,hi&0xff,lo>>>24&0xff,lo>>>16&0xff,lo>>>8&0xff,lo&0xff);
    var H0=0x67452301,H1=0xEFCDAB89,H2=0x98BADCFE,H3=0x10325476,H4=0xC3D2E1F0;
    for(var i=0;i<msg.length;i+=64){
        var W=[];
        for(var j=0;j<16;j++)W[j]=msg[i+j*4]<<24|msg[i+j*4+1]<<16|msg[i+j*4+2]<<8|msg[i+j*4+3];
        for(var j=16;j<80;j++)W[j]=rotl(W[j-3]^W[j-8]^W[j-14]^W[j-16],1);
        var a=H0,b=H1,c=H2,d=H3,e=H4;
        for(var j=0;j<80;j++){
            var f,k;
            if(j<20){f=(b&c)|(~b&d);k=0x5A827999}
            else if(j<40){f=b^c^d;k=0x6ED9EBA1}
            else if(j<60){f=(b&c)|(b&d)|(c&d);k=0x8F1BBCDC}
            else{f=b^c^d;k=0xCA62C1D6}
            var temp=(rotl(a,5)+f+e+k+W[j])>>>0;
            e=d;d=c;c=rotl(b,30)>>>0;b=a;a=temp;
        }
        H0=(H0+a)>>>0;H1=(H1+b)>>>0;H2=(H2+c)>>>0;H3=(H3+d)>>>0;H4=(H4+e)>>>0;
    }
    return tohex(H0)+tohex(H1)+tohex(H2)+tohex(H3)+tohex(H4);
}
</script>
</head>
<body>
<h2>Academy Login</h2>
<?php if ($error): ?><p style="color:red"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>
<form method="post" onsubmit="return encrypt_password()">
<input type="hidden" name="token" value="<?php echo htmlspecialchars($_SESSION['token']); ?>">
<input type="hidden" name="form_password_hidden" value="">
<label>Username: <input type="text" name="form_login"></label><br><br>
<label>Password: <input type="password" name="form_password"></label><br><br>
<input type="submit" name="submit" value="Login">
</form>
</body>
</html>
