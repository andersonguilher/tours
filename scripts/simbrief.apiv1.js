/*
* SimBrief APIv1 Javascript Functions
* Modified for Modal/Iframe support + Loading Screen by Google Gemini
* Based on original by Derek Mayer
*/

var api_dir = '../includes/';

/*
* Settings and initial variables
*/

var sbform = "sbapiform";
var sbworkerurl = "https://www.simbrief.com/ofp/ofp.loader.api.php";
var sbworkerid = 'SBworker';
var sbcallerid = 'SBcaller';
var sbworkerstyle = 'width=600,height=315';
var sbworker;
var SBloop;

var ofp_id;

var outputpage_save;
var outputpage_calc;
var fe_result;

var timestamp;
var api_code;
var check_interval_count = 0;

function simbriefsubmit(outputpage) {
  /*
  * Ensure any prior requests are cleaned up before continuing..
  */

  if (sbworker) {
    // Tenta fechar se for popup
    if (typeof sbworker.close === 'function') { sbworker.close(); }
  }

  if (SBloop) {
    window.clearInterval(SBloop);
  }

  api_code = null;
  ofp_id = null;
  fe_result = null;
  timestamp = null;
  outputpage_save = null;
  outputpage_calc = null;
  check_interval_count = 0;

  do_simbriefsubmit(outputpage);
}

function do_simbriefsubmit(outputpage) {
  // Lógica para detectar se usamos Modal ou Popup
  var iframeModal = document.getElementById('sb-iframe-modal');
  if (iframeModal) {
    sbworkerid = 'sb_iframe'; // Nome do Iframe no PHP
  } else {
    sbworkerid = 'SBworker'; // Fallback para Popup
  }

  //CATCH UNDEFINED OUTPUT PAGE, SET IT TO THE CURRENT PAGE
  if (outputpage == null || outputpage == false) {
    outputpage = location.href;
  }

  if (timestamp == null || timestamp == false) {
    timestamp = Math.round(+new Date() / 1000);
  }

  outputpage_save = outputpage;
  outputpage_calc = outputpage.replace("http://", "").replace("https://", ""); // Remove ambos para evitar erros

  //MAKESHIFT LOOP IN CASE IT TAKES A MOMENT TO LOAD THE API_CODE VARIABLE
  if (api_code == null || api_code == false || typeof (api_code) == 'undefined') {
    api_code = 'notset';
    sb_res_load(api_dir + 'simbrief.apiv1.php?api_req=' + document.getElementsByName('orig')[0].value + document.getElementsByName('dest')[0].value + document.getElementsByName('type')[0].value + timestamp + outputpage_calc);
    setTimeout(function () { do_simbriefsubmit(outputpage); }, 500);
    return;
  }
  else if (api_code == 'notset') {
    setTimeout(function () { do_simbriefsubmit(outputpage); }, 500);
    return;
  }

  //IF API_CODE IS SET, FINALIZE FORM
  var apiform = document.getElementById(sbform);
  apiform.setAttribute("method", "get");
  apiform.setAttribute("action", sbworkerurl);
  apiform.setAttribute("target", sbworkerid);

  var input = document.createElement("input");
  input.setAttribute("type", "hidden");
  input.setAttribute("name", "apicode");
  input.setAttribute("value", api_code);
  apiform.appendChild(input);

  var input = document.createElement("input");
  input.setAttribute("type", "hidden");
  input.setAttribute("name", "outputpage");
  input.setAttribute("value", outputpage_calc);
  apiform.appendChild(input);

  var input = document.createElement("input");
  input.setAttribute("type", "hidden");
  input.setAttribute("name", "timestamp");
  input.setAttribute("value", timestamp);
  apiform.appendChild(input);

  //LAUNCH FORM
  window.name = sbcallerid;
  LaunchSBworker();
  apiform.submit();

  //DETERMINE OFP_ID
  ofp_id = timestamp + '_' + md5(document.getElementsByName('orig')[0].value + document.getElementsByName('dest')[0].value + document.getElementsByName('type')[0].value);

  //LOOP TO DETECT WHEN THE WORKER PROCESS IS CLOSED
  SBloop = window.setInterval(checkSBworker, 1000);
}

/*
* Other related functions
*/

function LaunchSBworker() {
  var modal = document.getElementById('sb-iframe-modal');
  if (modal) {
    // MODO MODAL
    modal.classList.remove('hidden'); // Exibe o modal
    var iframe = document.getElementById('sb_iframe');
    if (iframe) iframe.src = 'about:blank'; // Limpa estado anterior
    sbworker = null;
  } else {
    // MODO POPUP (Original)
    sbworker = window.open('about:blank', sbworkerid, sbworkerstyle);

    if (sbworker == null || typeof (sbworker) == 'undefined') {
      alert('Please disable your pop-up blocker to generate a flight plan!');
    }
    else {
      if (window.focus) sbworker.focus();
    }
  }
}

function checkSBworker() {
  var modal = document.getElementById('sb-iframe-modal');
  var isFinished = false;

  if (modal && !modal.classList.contains('hidden')) {
    // --- LÓGICA DO MODAL ---
    var iframe = document.getElementById('sb_iframe');
    try {
      // Tenta ler a URL do iframe
      var href = iframe.contentWindow.location.href;

      // SE conseguir ler a URL, significa que o iframe NÃO está mais no SimBrief (Cross-Origin),
      // mas sim de volta ao nosso domínio (Same-Origin) ou about:blank.

      if (href && href !== 'about:blank' && href.indexOf('simbrief.com') === -1) {
        // Confirmamos que voltou para o nosso site (kafly.com.br...)
        isFinished = true;
      }
    } catch (e) {
      // SE der erro ao tentar ler, é SINAL DE QUE ESTÁ FUNCIONANDO.
      // Significa que o usuário está na página do SimBrief gerando o plano.
      // O navegador bloqueia leitura de domínios cruzados. Aguardamos.
    }
  } else {
    // --- LÓGICA DO POPUP (Original) ---
    if (sbworker && sbworker.closed) {
      isFinished = true;
    }
  }

  // VERIFICAÇÃO DE BACKUP (Polling no Servidor)
  check_interval_count++;
  if (check_interval_count % 3 === 0) { // Verifica a cada 3 segundos
    sb_res_load(api_dir + 'simbrief.apiv1.php?js_url_check=' + ofp_id + "&var=fe_result");
    if (fe_result == 'true') {
      isFinished = true;
    }
  }

  // FINALIZAÇÃO
  if (isFinished) {
    window.clearInterval(SBloop);

    // ** NOVO: CHAMA A TELA DE CARREGAMENTO PARA PREENCHER O GAP **
    if (typeof showLoadingScreen === 'function') {
      showLoadingScreen();
    } else if (modal) {
      modal.classList.add('hidden'); // Fallback antigo
    }

    Redirect_caller(); // Recarrega a página principal com os dados
  }
}

function Redirect_caller() {
  /*
  * First check that the file actually exists.
  */
  if (fe_result == null || fe_result == false || typeof (fe_result) == 'undefined') {
    fe_result = 'notset';
    sb_res_load(api_dir + 'simbrief.apiv1.php?js_url_check=' + ofp_id + "&var=fe_result");
    setTimeout(function () { Redirect_caller(); }, 500);
    return;
  }
  else if (fe_result == 'notset') {
    setTimeout(function () { Redirect_caller(); }, 500);
    return;
  }

  //IF FE_RESULT IS SET, CONTINUE     
  if (fe_result == 'true') {
    var apiform = document.createElement("form");
    apiform.setAttribute("method", "get");
    apiform.setAttribute("action", outputpage_save);

    var urlinfo = urlObject({ 'url': outputpage_save });
    for (var key in urlinfo['parameters']) {
      var input = document.createElement("input");
      input.setAttribute("type", "hidden");
      input.setAttribute("name", key);
      input.setAttribute("value", urlinfo['parameters'][key]);
      apiform.appendChild(input);
    }

    var input = document.createElement("input");
    input.setAttribute("type", "hidden");
    input.setAttribute("name", "ofp_id");
    input.setAttribute("value", ofp_id);
    apiform.appendChild(input);

    document.body.appendChild(apiform);
    apiform.submit();
  }
}

function sb_res_load(url) {
  var fileref = document.createElement('script');
  fileref.type = "text/javascript";
  fileref.src = url + "&p=" + Math.floor(Math.random() * 10000000);
  document.getElementsByTagName("head")[0].appendChild(fileref);
}

/*
* URLOBJECT function
* Courtesy Ayman Farhat
*/
function urlObject(options) {
  "use strict";
  var url_search_arr, option_key, i, urlObj, get_param, key, val, url_query, url_get_params = {},
    a = document.createElement('a'),
    default_options = { 'url': window.location.href, 'unescape': true, 'convert_num': true };

  if (typeof options !== "object") { options = default_options; } else {
    for (option_key in default_options) {
      if (default_options.hasOwnProperty(option_key)) {
        if (options[option_key] === undefined) { options[option_key] = default_options[option_key]; }
      }
    }
  }

  a.href = options.url;
  url_query = a.search.substring(1);
  url_search_arr = url_query.split('&');

  if (url_search_arr[0].length > 1) {
    for (i = 0; i < url_search_arr.length; i += 1) {
      get_param = url_search_arr[i].split("=");
      if (options.unescape) { key = decodeURI(get_param[0]); val = decodeURI(get_param[1]); } else { key = get_param[0]; val = get_param[1]; }
      if (options.convert_num) {
        if (val.match(/^\d+$/)) { val = parseInt(val, 10); } else if (val.match(/^\d+\.\d+$/)) { val = parseFloat(val); }
      }
      if (url_get_params[key] === undefined) { url_get_params[key] = val; } else if (typeof url_get_params[key] === "string") { url_get_params[key] = [url_get_params[key], val]; } else { url_get_params[key].push(val); }
      get_param = [];
    }
  }
  urlObj = { protocol: a.protocol, hostname: a.hostname, host: a.host, port: a.port, hash: a.hash.substr(1), pathname: a.pathname, search: a.search, parameters: url_get_params };
  return urlObj;
}

/*
* MD5 and UTF8_ENCODE functions
*/
function md5(str) {
  var xl;
  var rotateLeft = function (lValue, iShiftBits) { return (lValue << iShiftBits) | (lValue >>> (32 - iShiftBits)); };
  var addUnsigned = function (lX, lY) {
    var lX4, lY4, lX8, lY8, lResult;
    lX8 = (lX & 0x80000000); lY8 = (lY & 0x80000000); lX4 = (lX & 0x40000000); lY4 = (lY & 0x40000000);
    lResult = (lX & 0x3FFFFFFF) + (lY & 0x3FFFFFFF);
    if (lX4 & lY4) { return (lResult ^ 0x80000000 ^ lX8 ^ lY8); }
    if (lX4 | lY4) { if (lResult & 0x40000000) { return (lResult ^ 0xC0000000 ^ lX8 ^ lY8); } else { return (lResult ^ 0x40000000 ^ lX8 ^ lY8); } } else { return (lResult ^ lX8 ^ lY8); }
  };
  var _F = function (x, y, z) { return (x & y) | ((~x) & z); };
  var _G = function (x, y, z) { return (x & z) | (y & (~z)); };
  var _H = function (x, y, z) { return (x ^ y ^ z); };
  var _I = function (x, y, z) { return (y ^ (x | (~z))); };
  var _FF = function (a, b, c, d, x, s, ac) { a = addUnsigned(a, addUnsigned(addUnsigned(_F(b, c, d), x), ac)); return addUnsigned(rotateLeft(a, s), b); };
  var _GG = function (a, b, c, d, x, s, ac) { a = addUnsigned(a, addUnsigned(addUnsigned(_G(b, c, d), x), ac)); return addUnsigned(rotateLeft(a, s), b); };
  var _HH = function (a, b, c, d, x, s, ac) { a = addUnsigned(a, addUnsigned(addUnsigned(_H(b, c, d), x), ac)); return addUnsigned(rotateLeft(a, s), b); };
  var _II = function (a, b, c, d, x, s, ac) { a = addUnsigned(a, addUnsigned(addUnsigned(_I(b, c, d), x), ac)); return addUnsigned(rotateLeft(a, s), b); };
  var convertToWordArray = function (str) {
    var lWordCount; var lMessageLength = str.length; var lNumberOfWords_temp1 = lMessageLength + 8; var lNumberOfWords_temp2 = (lNumberOfWords_temp1 - (lNumberOfWords_temp1 % 64)) / 64; var lNumberOfWords = (lNumberOfWords_temp2 + 1) * 16; var lWordArray = new Array(lNumberOfWords - 1); var lBytePosition = 0; var lByteCount = 0;
    while (lByteCount < lMessageLength) { lWordCount = (lByteCount - (lByteCount % 4)) / 4; lBytePosition = (lByteCount % 4) * 8; lWordArray[lWordCount] = (lWordArray[lWordCount] | (str.charCodeAt(lByteCount) << lBytePosition)); lByteCount++; }
    lWordCount = (lByteCount - (lByteCount % 4)) / 4; lBytePosition = (lByteCount % 4) * 8; lWordArray[lWordCount] = lWordArray[lWordCount] | (0x80 << lBytePosition); lWordArray[lNumberOfWords - 2] = lMessageLength << 3; lWordArray[lNumberOfWords - 1] = lMessageLength >>> 29; return lWordArray;
  };
  var wordToHex = function (lValue) { var wordToHexValue = '', wordToHexValue_temp = '', lByte, lCount; for (lCount = 0; lCount <= 3; lCount++) { lByte = (lValue >>> (lCount * 8)) & 255; wordToHexValue_temp = '0' + lByte.toString(16); wordToHexValue = wordToHexValue + wordToHexValue_temp.substr(wordToHexValue_temp.length - 2, 2); } return wordToHexValue; };
  var x = [], k, AA, BB, CC, DD, a, b, c, d, S11 = 7, S12 = 12, S13 = 17, S14 = 22, S21 = 5, S22 = 9, S23 = 14, S24 = 20, S31 = 4, S32 = 11, S33 = 16, S34 = 23, S41 = 6, S42 = 10, S43 = 15, S44 = 21;
  str = utf8_encode(str); x = convertToWordArray(str); a = 0x67452301; b = 0xEFCDAB89; c = 0x98BADCFE; d = 0x10325476;
  for (k = 0; k < x.length; k += 16) {
    AA = a; BB = b; CC = c; DD = d;
    a = _FF(a, b, c, d, x[k + 0], S11, 0xD76AA478); d = _FF(d, a, b, c, x[k + 1], S12, 0xE8C7B756); c = _FF(c, d, a, b, x[k + 2], S13, 0x242070DB); b = _FF(b, c, d, a, x[k + 3], S14, 0xC1BDCEEE);
    a = _FF(a, b, c, d, x[k + 4], S11, 0xF57C0FAF); d = _FF(d, a, b, c, x[k + 5], S12, 0x4787C62A); c = _FF(c, d, a, b, x[k + 6], S13, 0xA8304613); b = _FF(b, c, d, a, x[k + 7], S14, 0xFD469501);
    a = _FF(a, b, c, d, x[k + 8], S11, 0x698098D8); d = _FF(d, a, b, c, x[k + 9], S12, 0x8B44F7AF); c = _FF(c, d, a, b, x[k + 10], S13, 0xFFFF5BB1); b = _FF(b, c, d, a, x[k + 11], S14, 0x895CD7BE);
    a = _FF(a, b, c, d, x[k + 12], S11, 0x6B901122); d = _FF(d, a, b, c, x[k + 13], S12, 0xFD987193); c = _FF(c, d, a, b, x[k + 14], S13, 0xA679438E); b = _FF(b, c, d, a, x[k + 15], S14, 0x49B40821);
    a = _GG(a, b, c, d, x[k + 1], S21, 0xF61E2562); d = _GG(d, a, b, c, x[k + 6], S22, 0xC040B340); c = _GG(c, d, a, b, x[k + 11], S23, 0x265E5A51); b = _GG(b, c, d, a, x[k + 0], S24, 0xE9B6C7AA);
    a = _GG(a, b, c, d, x[k + 5], S21, 0xD62F105D); d = _GG(d, a, b, c, x[k + 10], S22, 0x2441453); c = _GG(c, d, a, b, x[k + 15], S23, 0xD8A1E681); b = _GG(b, c, d, a, x[k + 4], S24, 0xE7D3FBC8);
    a = _GG(a, b, c, d, x[k + 9], S21, 0x21E1CDE6); d = _GG(d, a, b, c, x[k + 14], S22, 0xC33707D6); c = _GG(c, d, a, b, x[k + 3], S23, 0xF4D50D87); b = _GG(b, c, d, a, x[k + 8], S24, 0x455A14ED);
    a = _GG(a, b, c, d, x[k + 13], S21, 0xA9E3E905); d = _GG(d, a, b, c, x[k + 2], S22, 0xFCEFA3F8); c = _GG(c, d, a, b, x[k + 7], S23, 0x676F02D9); b = _GG(b, c, d, a, x[k + 12], S24, 0x8D2A4C8A);
    a = _HH(a, b, c, d, x[k + 5], S31, 0xFFFA3942); d = _HH(d, a, b, c, x[k + 8], S32, 0x8771F681); c = _HH(c, d, a, b, x[k + 11], S33, 0x6D9D6122); b = _HH(b, c, d, a, x[k + 14], S34, 0xFDE5380C);
    a = _HH(a, b, c, d, x[k + 1], S31, 0xA4BEEA44); d = _HH(d, a, b, c, x[k + 4], S32, 0x4BDECFA9); c = _HH(c, d, a, b, x[k + 7], S33, 0xF6BB4B60); b = _HH(b, c, d, a, x[k + 10], S34, 0xBEBFBC70);
    a = _HH(a, b, c, d, x[k + 13], S31, 0x289B7EC6); d = _HH(d, a, b, c, x[k + 0], S32, 0xEAA127FA); c = _HH(c, d, a, b, x[k + 3], S33, 0xD4EF3085); b = _HH(b, c, d, a, x[k + 6], S34, 0x4881D05);
    a = _HH(a, b, c, d, x[k + 9], S31, 0xD9D4D039); d = _HH(d, a, b, c, x[k + 12], S32, 0xE6DB99E5); c = _HH(c, d, a, b, x[k + 15], S33, 0x1FA27CF8); b = _HH(b, c, d, a, x[k + 2], S34, 0xC4AC5665);
    a = _II(a, b, c, d, x[k + 0], S41, 0xF4292244); d = _II(d, a, b, c, x[k + 7], S42, 0x432AFF97); c = _II(c, d, a, b, x[k + 14], S43, 0xAB9423A7); b = _II(b, c, d, a, x[k + 5], S44, 0xFC93A039);
    a = _II(a, b, c, d, x[k + 12], S41, 0x655B59C3); d = _II(d, a, b, c, x[k + 3], S42, 0x8F0CCC92); c = _II(c, d, a, b, x[k + 10], S43, 0xFFEFF47D); b = _II(b, c, d, a, x[k + 1], S44, 0x85845DD1);
    a = _II(a, b, c, d, x[k + 8], S41, 0x6FA87E4F); d = _II(d, a, b, c, x[k + 15], S42, 0xFE2CE6E0); c = _II(c, d, a, b, x[k + 6], S43, 0xA3014314); b = _II(b, c, d, a, x[k + 13], S44, 0x4E0811A1);
    a = _II(a, b, c, d, x[k + 4], S41, 0xF7537E82); d = _II(d, a, b, c, x[k + 11], S42, 0xBD3AF235); c = _II(c, d, a, b, x[k + 2], S43, 0x2AD7D2BB); b = _II(b, c, d, a, x[k + 9], S44, 0xEB86D391);
    a = addUnsigned(a, AA); b = addUnsigned(b, BB); c = addUnsigned(c, CC); d = addUnsigned(d, DD);
  }
  return (wordToHex(a) + wordToHex(b) + wordToHex(c) + wordToHex(d)).toUpperCase().substr(0, 10);
}

function utf8_encode(argString) {
  if (argString === null || typeof argString === 'undefined') return '';
  var string = (argString + '');
  var utftext = '', start, end, stringl = 0;
  start = end = 0; stringl = string.length;
  for (var n = 0; n < stringl; n++) {
    var c1 = string.charCodeAt(n);
    var enc = null;
    if (c1 < 128) end++;
    else if (c1 > 127 && c1 < 2048) enc = String.fromCharCode((c1 >> 6) | 192, (c1 & 63) | 128);
    else if ((c1 & 0xF800) != 0xD800) enc = String.fromCharCode((c1 >> 12) | 224, ((c1 >> 6) & 63) | 128, (c1 & 63) | 128);
    else {
      var c2 = string.charCodeAt(++n);
      c1 = ((c1 & 0x3FF) << 10) + (c2 & 0x3FF) + 0x10000;
      enc = String.fromCharCode((c1 >> 18) | 240, ((c1 >> 12) & 63) | 128, ((c1 >> 6) & 63) | 128, (c1 & 63) | 128);
    }
    if (enc !== null) { if (end > start) utftext += string.slice(start, end); utftext += enc; start = end = n + 1; }
  }
  if (end > start) utftext += string.slice(start, stringl);
  return utftext;
}