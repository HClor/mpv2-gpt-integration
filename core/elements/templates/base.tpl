<!doctype html>
<html lang="en">
<head>
    <title>[[*pagetitle]] - [[++site_name]]</title>
    <base href="[[!++site_url]]" />
    <meta charset="[[++modx_charset]]" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />

    <link rel="stylesheet" href="/assets/templates/css/base-layout.css">
</head>
<body>

<a href="http://www.modx.com" title="Modx" class="logo" target="_blank">MODX</a>

<div class="container">
    <section>
        <h1>[[*longtitle:default=`[[*pagetitle]]`]]</h1>
        [[*content]]
    </section>
    <aside>
        <a href="[[++manager_url]]" title="Your MODX manager" class="cta-button">Go to the&nbsp;manager</a>
        <h3>Learn more about&nbsp;MODX</h3>
        <ul>
            <li><a href="https://docs.modx.com/current/en/index">Official&nbsp;Documentation</a></li>
            <li><a href="https://docs.modx.com/current/en/getting-started/friendly-urls">Using Friendly&nbsp;URLs</a></li>
            <li><a href="https://docs.modx.com/current/en/building-sites/extras">Package&nbsp;Management</a></li>
            <li><a href="http://modx.com/blog/">Official MODX&nbsp;Blog</a></li>
            <li><a href="http://www.discovermodx.com/">Discover&nbsp;MODX</a></li>
            <li><a href="https://modx.today">MODX.today</a></li>
        </ul>

        <h3>Get help!</h3>
        <ul>
            <li><a href="http://forums.modx.com/">Official MODX&nbsp;Forums</a></li>
            <li><a href="https://modx.org/">MODX on&nbsp;Slack</a></li>
            <li><a href="https://twitter.com/modx">MODX on&nbsp;Twitter</a></li>
            <li><a href="https://www.facebook.com/modxcms">MODX on&nbsp;Facebook</a></li>
            <li><a href="http://modx.com/professionals/">Find a MODX&nbsp;Professional</a></li>
        </ul>
    </aside>
    <div class="companies">
        <h3>Extend MODX with&nbsp;Extras</h3>
        <ul>
            <li class="modxextras"><a href="http://modx.com/extras/" title="MODX extras" target="_blank">MODX&nbsp;extras</a></li>
            <li class="modmore"><a href="https://www.modmore.com/extras/" title="modmore.com" target="_blank">modmore.com</a></li>
            <li class="modstore"><a href="https://modstore.pro/" title="modstore.pro" target="_blank">modstore.pro</a></li>
            <li class="extrasio"><a href="https://extras.io/extras/" title="Extras.io" target="_blank">Extras.io</a></li>
        </ul>
    </div>
</div>
<footer class="disclaimer">
    <p>&copy; 2005-present the <a href="http://www.modx.com/" target="_blank">MODX</a> Content Management Framework (CMF) project. All rights reserved. MODX is licensed under the GNU&nbsp;GPL.</p>
</footer>

<script>
    // Load the Open Sans font
    try {
        document.addEventListener("DOMContentLoaded", function() { // prevent a Flash Of Unstyled Text (FOUT)
            document.querySelector('head').innerHTML += "<link href='https://fonts.googleapis.com/css?family=Open+Sans:400,700&display=swap' rel='stylesheet' type='text/css'>";
            document.body.classList.add('loaded');
        });
    } catch (e) { }

    // Shuffle the vendors to prevent favoritism of one vendor over the other
    // with thanks to http://james.padolsey.com/javascript/shuffling-the-dom/
    function shuffle(elems) {
        var allElems = (function(){
            var ret = [], l = elems.length;
            while (l--) {
                if (elems[l].className !== 'modxextras') {
                    ret[ret.length] = elems[l];
                }
            }
            return ret;
        })();

        var shuffled = (function(){
            var l = allElems.length, ret = [];
            while (l--) {
                var random = Math.floor(Math.random() * allElems.length),
                        randEl = allElems[random].cloneNode(true);
                allElems.splice(random, 1);
                ret[ret.length] = randEl;
            }
            return ret;
        })(), l = elems.length;

        // To make sure the MODX logo stays #1, we lower the count by one here (shuffling 3 instead of 4 items)
        // and refer to elems[l+1] in the loop below. This matches because allElems was also filtered to not include
        // the official MODX logo.
        l--;
        while (l--) {
            elems[l+1].parentNode.insertBefore(shuffled[l], elems[l+1].nextSibling);
            elems[l+1].parentNode.removeChild(elems[l+1]);
        }
    }
    shuffle(document.querySelectorAll('.companies li'));
</script>

</body>
</html>
