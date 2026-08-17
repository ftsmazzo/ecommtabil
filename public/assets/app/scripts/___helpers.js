// REMOVE OS ACENTOS DE UM TEXTO
function removeAcento(text)
{
    text = text.toLowerCase();
    text = text.replace(new RegExp('[ÁÀÂÃ]','gi'), 'a');
    text = text.replace(new RegExp('[ÉÈÊ]','gi'), 'e');
    text = text.replace(new RegExp('[ÍÌÎ]','gi'), 'i');
    text = text.replace(new RegExp('[ÓÒÔÕ]','gi'), 'o');
    text = text.replace(new RegExp('[ÚÙÛ]','gi'), 'u');
    text = text.replace(new RegExp('[Ç]','gi'), 'c');
    return text;
}

// ESCONDE UM ELEMENTO SEM JQUERY
function hide(elements) {
    elements = elements.length ? elements : [elements];
    for (let index = 0; index < elements.length; index++) {
      elements[index].style.display = 'none';
    }
}

// MOSTRA UM ELEMENTO SEM JQUERY
function show(elements) {
    elements = elements.length ? elements : [elements];
    for (let index = 0; index < elements.length; index++) {
      elements[index].style.display = 'block';
    }
}

// AJAX SEM JQUERY
// USAR: ajax.get OU ajax.post
var ajax = {};
ajax.x = function () {
    if (typeof XMLHttpRequest !== 'undefined') {
        return new XMLHttpRequest();
    }
    var versions = [
        "MSXML2.XmlHttp.6.0",
        "MSXML2.XmlHttp.5.0",
        "MSXML2.XmlHttp.4.0",
        "MSXML2.XmlHttp.3.0",
        "MSXML2.XmlHttp.2.0",
        "Microsoft.XmlHttp"
    ];

    var xhr;
    for (var i = 0; i < versions.length; i++) {
        try {
            xhr = new ActiveXObject(versions[i]);
            break;
        } catch (e) {
        }
    }
    return xhr;
};

ajax.send = function (url, callback, method, data, async) {
    if (async === undefined) {
        async = true;
    }
    var x = ajax.x();
    x.open(method, url, async);
    x.onreadystatechange = function () {
        if (x.readyState == 4) {
            callback(x.responseText)
        }
    };
    if (method == 'POST') {
        x.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
    }
    x.send(data)
};

ajax.get = function (url, data, callback, async) {
    var query = [];
    for (var key in data) {
        query.push(encodeURIComponent(key) + '=' + encodeURIComponent(data[key]));
    }
    ajax.send(url + (query.length ? '?' + query.join('&') : ''), callback, 'GET', null, async)
};

ajax.post = function (url, data, callback, async) {
    var query = [];
    for (var key in data) {
        query.push(encodeURIComponent(key) + '=' + encodeURIComponent(data[key]));
    }
    ajax.send(url, callback, 'POST', query.join('&'), async)
};

// CHECAR VISIBILIDADE DE ELEMENTOS SEM JQUERY
function getStyle(elem, prop) {
    if (elem.currentStyle) { //IE8

        // Converte hifens para camelCase
        prop = prop.replace(/-([a-z])/gi, function (value) {
            return value.toLowerCase();
        });

        return elem.currentStyle[prop];

    } else if (window.getComputedStyle) {//Navegadores modernos

        // Converte camelCase para hifens
        prop = prop.replace(/([A-Z])/g, '-$1').toLowerCase();

        return window.getComputedStyle(elem, null).getPropertyValue(prop);
    }
}

// VERIFICA SE ELEMENTO É VISIVEL
function isVisible(elem) {
    //Verifica inputs
    if (/^(input|select|textarea)$/i.test(elem.nodeName) && elem.type === "hidden") {
        return false;
    }

    //Verifica a propriedade visibility
    if (getStyle(elem, "visibility") === "hidden") {
        return false;
    }

    //Verifica a propriedade display
    if (getStyle(elem, "display") === "none") {
        return false;
    }

    return true;
}

// VERIFICA SE ELEMENTO NÃO É VISIVEL
function isHidden(elem) {
     return !isVisible(elem);
}

// ARRASTAR ELEMENTO
function dragElement(elem) {
    var pos1 = 0, pos2 = 0, pos3 = 0, pos4 = 0;
    if (document.getElementById(elem.id + "header")) {
      /* if present, the header is where you move the DIV from:*/
      document.getElementById(elem.id + "header").onmousedown = dragMouseDown;
    } else {
      /* otherwise, move the DIV from anywhere inside the DIV:*/
      elem.onmousedown = dragMouseDown;
    }

    function dragMouseDown(e) {
      e = e || window.event;
      e.preventDefault();
      // get the mouse cursor position at startup:
      pos3 = e.clientX;
      pos4 = e.clientY;
      document.onmouseup = closeDragElement;
      // call a function whenever the cursor moves:
      document.onmousemove = elementDrag;
    }

    function elementDrag(e) {
      e = e || window.event;
      e.preventDefault();
      // calculate the new cursor position:
      pos1 = pos3 - e.clientX;
      pos2 = pos4 - e.clientY;
      pos3 = e.clientX;
      pos4 = e.clientY;
      // set the element's new position:
      elem.style.top = (elem.offsetTop - pos2) + "px";
      elem.style.left = (elem.offsetLeft - pos1) + "px";
    }

    function closeDragElement() {
      /* stop moving when mouse button is released:*/
      document.onmouseup = null;
      document.onmousemove = null;
    }
}

// REMOVE TAGS HTML DE UM TEXTO
function stripHtmlToText(html) {
    var tmp = document.createElement("DIV");
    tmp.innerHTML = html;
    var res = tmp.textContent || tmp.innerText || '';
    res.replace('\u200B', ''); // zero width space
    res = res.trim();
    return res;
}

// FORMATA NÚMERO PARA REAL BRASILEIRO
function formatReal( valor ) {
    valor = valor + '';
    valor = parseInt(valor.replace(/[\D]+/g,''));
    valor = valor + '';
    valor = valor.replace(/([0-9]{2})$/g, ",$1");

    if (valor.length > 6) {
      valor = valor.replace(/([0-9]{3}),([0-9]{2}$)/g, ".$1,$2");
    }

    return valor;
}

// FORMATA DATA PADRÃO PARA O FORMATO BRASILEIRO DD/MM/YYYY
function formatDate( date ) {
    if (date) {
        var data = date.split("-");
        return data.reverse().join("/");
    }
}

// REMOVER ACENTOS E ESPAÇOS DE UMA STRING
function string_to_slug(str, separator) {
    str = str.trim();
    str = str.toLowerCase();

    // remove accents, swap ñ for n, etc
    const from = "åàáãäâèéëêìíïîòóöôùúüûñç·/_,:;";
    const to = "aaaaaaeeeeiiiioooouuuunc------";

    for (let i = 0, l = from.length; i < l; i++) {
        str = str.replace(new RegExp(from.charAt(i), "g"), to.charAt(i));
    }

    return str
        .replace(/[^a-z0-9 -]/g, "") // remove invalid chars
        .replace(/\s+/g, "-") // collapse whitespace and replace by -
        .replace(/-+/g, "-") // collapse dashes
        .replace(/^-+/, "") // trim - from start of text
        .replace(/-+$/, "") // trim - from end of text
        .replace(/-/g, separator);
}

// FORMATAR NÚMERO EM MOEDA PARA FLOAT
function moeda2Float(valor) {

    if(valor === ""){
       valor =  0;
    }else{
       valor = valor.replace(".","");
       valor = valor.replace(",",".");
       valor = parseFloat(valor).toFixed(2);
    }
    return valor;

 }

 // FORMATAR NÚMERO FLOAT PARA MOEDA
 function float2Moeda(valor) {

    var inteiro = null, decimal = null, c = null, j = null;
    var aux = new Array();
    valor = ""+valor;
    c = valor.indexOf(".",0);
    //encontrou o ponto na string
    if(c > 0){
       //separa as partes em inteiro e decimal
       inteiro = valor.substring(0,c);
       decimal = valor.substring(c+1,valor.length);
    }else{
       inteiro = valor;
    }

    //pega a parte inteiro de 3 em 3 partes
    for (j = inteiro.length, c = 0; j > 0; j-=3, c++){
       aux[c]=inteiro.substring(j-3,j);
    }

    //percorre a string acrescentando os pontos
    inteiro = "";
    for(c = aux.length-1; c >= 0; c--){
       inteiro += aux[c]+'.';
    }
    //retirando o ultimo ponto e finalizando a parte inteiro

    inteiro = inteiro.substring(0,inteiro.length-1);

    decimal = parseInt(decimal);
    if(isNaN(decimal)){
       decimal = "00";
    }else{
       decimal = ""+decimal;
       if(decimal.length === 1){
          decimal = decimal+"0";
       }
    }

    valor = inteiro+","+decimal;

    return valor;

 }

 // DEIXAR A TELA DO NAVEGADOR EM FULLSCREEN (F11)
 function fullScreen() {

    // SE O ESTADO ATUAL FOR "fullscreen", DESATIVÁ-LO.
    // NOTE QUE PARA AS VERIFICAÇÕES É FEITO UMA VALIDAÇÃO PARA TODOS OS POSSÍVEIS NAVEGADORES, FACILITANDO A SUA VIDA.
    if (document.fullscreenElement) {
        document.exitFullscreen();
        isFullScreen = false;
    } else if (document.webkitFullscreenElement) {
        document.webkitExitFullscreen();
        isFullScreen = false;
    } else if (document.mozFullScreenElement) {
        document.mozCancelFullScreen();
        isFullScreen = false;
    } else if (document.msFullScreenElement) {
        document.msExitFullscreen();
        isFullScreen = false;
    }

    if (elem.requestFullscreen) {
        elem.requestFullscreen();
        isFullScreen = true;
    } else if (elem.mozRequestFullScreen) {
        elem.mozRequestFullScreen();
        isFullScreen = true;
    } else if (elem.webkitRequestFullscreen) {
        elem.webkitRequestFullscreen();
        isFullScreen = true;
    } else if (elem.msRequestFullscreen) {
        elem.msRequestFullscreen();
        isFullScreen = true;
    }
}

// CONVERTER SEGUNDOS EM HORAS
function sec2Hour(secs) {

    var sec_num = parseInt(secs, 10)
    var hours   = Math.floor(sec_num / 3600)
    var minutes = Math.floor(sec_num / 60) % 60
    var seconds = sec_num % 60

    h = hours>0 ? hours + " h " : "";
    m = minutes>0 ? minutes + " m " : "";

    return h + m;
}

// LIMPA VALORES DO FORMULÁRIO DE CEP.
function limpa_formulario_cep() {
    $("#rua,#endereco,#logradouro,#bairro,#cidade,#uf,#estado").val("");
    $("input[name=rua],input[name=endereco],input[name=logradouro],input[name=bairro],input[name=cidade],select[name=cidade],input[name=uf],input[name=estado],select[name=uf],select[name=estado]").val("");
}

// FUNÇÃO PARA ADICIONAR CLASSES ANTES DAS EXISTENTES
jQuery.fn.extend({
    prependClass: function(newClasses) {
        return this.each(function() {
            var currentClasses = $(this).prop("class");
            $(this).removeClass(currentClasses).addClass(newClasses + " " + currentClasses);
        });
    }
});

// Clique no valor -> troca por input
// ---------- util: formata para pt-BR com 2 casas ----------
function formatarMoedaBR(valor) {
    if (valor === null || valor === undefined) return "0,00";
    // aceita "2", "2,5", "1.234,5", "1234.5", "1.234.567,89"
    // normaliza para número
    let s = String(valor).trim();
    // se usuário já usou pontos como milhares e vírgula como decimal, transformar p/ padrão JS
    s = s.replace(/\s+/g, '');
    // remove tudo exceto dígitos e , e .
    s = s.replace(/[^\d.,]/g, '');
    // se tiver vírgula e ponto, provavelmente ponto = milhares, vírgula = decimal
    if (s.indexOf(',') > -1 && s.indexOf('.') > -1) {
        s = s.replace(/\./g, '').replace(',', '.');
    } else {
        // se tiver só vírgula -> substitui por ponto para parseFloat
        s = s.replace(',', '.');
    }
    const numero = parseFloat(s);
    if (isNaN(numero)) return "0,00";
    return numero.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

// ---------- helper para forçar remoção do tooltip flutuante ----------
function removerTooltipsFlutuantes() {
    try {
        $('.tooltip.show').remove(); // remove qualquer tooltip visível que tenha ficado solto
    } catch (e) { /* ignora */ }
}

// ---------- função pra recriar tooltip na célula ----------
function restaurarTooltip($el) {
    // guarda título padrão
    const title = 'Clique para editar';
    // remove qualquer tooltip antiga antes de criar
    try { $el.tooltip('dispose'); } catch (e) { /* ignora */ }
    $el.attr('title', title).attr('data-original-title', title);
    // inicializa tooltip (Bootstrap 4/5 compatível)
    $el.tooltip({ trigger: 'hover' });
    // garante enabled
    try { $el.tooltip('enable'); } catch (e) { /* ignora */ }
}