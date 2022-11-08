function ready(fn) {
    if (document.readyState !== 'loading') {
        fn();
    } else {
        document.addEventListener('DOMContentLoaded', fn);
    }
}

ready(function() {
    var d = new Date();
    var el = document.querySelector('#i_tz_offset');
    var offset = tz_seconds_to_offset(d.getTimezoneOffset() * 60 * -1);
    el.value = offset;
});

function tz_seconds_to_offset(seconds) {
    var tz_offset = '';
    var hours = zero_pad(Math.floor(Math.abs(seconds / 60 / 60)));
    var minutes = zero_pad(Math.floor(seconds / 60) % 60);
    return (seconds < 0 ? '-' : '+') + hours + ":" + minutes;
}

function zero_pad(num) {
    num = "" + num;
    if (num.length == 1) {
        num = "0" + num;
    }
    return num;
}

