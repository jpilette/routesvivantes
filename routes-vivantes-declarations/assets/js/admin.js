(function () {
    'use strict';

    var endpoint = document.body.getAttribute('data-json');
    var mapNode = document.getElementById('rv-map');
    var form = document.getElementById('rv-period');
    var count = document.getElementById('rv-count');
    var button = document.getElementById('rv-filter');
    var panel = document.getElementById('rv-detail');
    var scrim = document.getElementById('rv-scrim');

    function setCount(text) {
        count.textContent = text;
    }

    if (!endpoint || !mapNode || !form) {
        return;
    }

    if (typeof L === 'undefined') {
        setCount('La carte ne s’affiche pas. Rechargez la page.');
        return;
    }

    var france = L.latLngBounds([41.2, -5.5], [51.2, 9.8]);
    var map = L.map(mapNode, {
        scrollWheelZoom: true,
        zoomControl: true
    });
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
    }).addTo(map);
    var layer = L.layerGroup().addTo(map);
    var fitPasses = 0;

    function fitFrance() {
        map.invalidateSize();
        if (mapNode.clientHeight < 100 || fitPasses >= 2) {
            return;
        }
        map.fitBounds(france, { animate: false, padding: [12, 12] });
        fitPasses += 1;
    }

    function iconFor(status) {
        var kind = status === 'blessé' ? 'pin pin-hurt' : 'pin pin-dead';
        return L.divIcon({
            className: 'pin-wrap',
            html: '<span class="' + kind + '"></span>',
            iconSize: [36, 36],
            iconAnchor: [18, 18]
        });
    }

    function formatCoord(value) {
        var number = Number(value);
        if (!isFinite(number)) {
            return '—';
        }
        return number.toFixed(5).replace('.', ',');
    }

    function countLabel(total) {
        if (total === 0) {
            return 'Aucun animal sur cette période.';
        }
        if (total === 1) {
            return '1 animal sur la carte.';
        }
        return total + ' animaux sur la carte.';
    }

    function closeDetail() {
        panel.hidden = true;
        scrim.hidden = true;
        if (button) {
            button.focus();
        }
    }

    function showPhoto(slot, url) {
        var image = document.getElementById('rv-photo-' + slot);
        var caption = document.getElementById('rv-caption-' + slot);
        if (url) {
            image.hidden = false;
            image.src = url;
            image.alt = 'Photo ' + slot + ' de l’animal';
            caption.textContent = 'Photo ' + slot;
            return;
        }
        image.hidden = true;
        image.removeAttribute('src');
        caption.textContent = 'Photo ' + slot + ' manquante.';
    }

    function openDetail(item) {
        document.getElementById('rv-detail-icon').textContent = item.icon || '';
        document.getElementById('rv-detail-animal').textContent = item.animal_label || 'Animal';
        var chip = document.getElementById('rv-detail-status');
        chip.textContent = item.status_label || '';
        chip.classList.toggle('is-hurt', item.status === 'blessé');
        document.getElementById('rv-detail-date').textContent = item.date_label || '';
        var remark = (item.remark || '').trim();
        document.getElementById('rv-detail-remark').textContent = remark || 'Pas de mot en plus.';
        document.getElementById('rv-detail-lat').textContent = formatCoord(item.lat);
        document.getElementById('rv-detail-lng').textContent = formatCoord(item.lng);
        showPhoto(1, item.photo_1 || '');
        showPhoto(2, item.photo_2 || '');
        scrim.hidden = false;
        panel.hidden = false;
        document.getElementById('rv-detail-close').focus();
    }

    function draw(items) {
        layer.clearLayers();
        closeQuiet();
        var shown = 0;
        items.forEach(function (item) {
            var lat = Number(item.lat);
            var lng = Number(item.lng);
            if (!isFinite(lat) || !isFinite(lng)) {
                return;
            }
            var title = (item.animal_label || 'Animal') + ', ' + (item.status_label || '');
            var marker = L.marker([lat, lng], {
                icon: iconFor(item.status),
                keyboard: true,
                title: title
            });
            marker.on('click', function () {
                openDetail(item);
            });
            marker.addTo(layer);
            shown += 1;
        });
        setCount(countLabel(shown));
    }

    function closeQuiet() {
        panel.hidden = true;
        scrim.hidden = true;
    }

    function load() {
        var from = form.from.value;
        var to = form.to.value;
        if (from && to && from > to) {
            setCount('La première date est après la seconde.');
            return;
        }

        button.disabled = true;
        var previous = button.textContent;
        button.textContent = 'Chargement…';
        var url = new URL(endpoint, window.location.origin);
        if (from) {
            url.searchParams.set('from', from);
        }
        if (to) {
            url.searchParams.set('to', to);
        }

        fetch(url.toString(), {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' }
        }).then(function (response) {
            if (response.redirected && response.url.indexOf('wp-login') !== -1) {
                window.location.href = response.url;
                return null;
            }
            var type = response.headers.get('content-type') || '';
            if (type.indexOf('application/json') === -1) {
                throw new Error('not-json');
            }
            return response.json();
        }).then(function (data) {
            button.disabled = false;
            button.textContent = previous;
            if (!data) {
                return;
            }
            if (data.error) {
                layer.clearLayers();
                closeQuiet();
                setCount(data.error);
                return;
            }
            if (!data.declarations || !Array.isArray(data.declarations)) {
                setCount('La carte n’a pas reçu les signalements. Rechargez la page.');
                return;
            }
            draw(data.declarations);
        }).catch(function () {
            button.disabled = false;
            button.textContent = previous;
            setCount('La carte n’a pas reçu les signalements. Vérifiez la connexion. Puis touchez Afficher.');
        });
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        load();
    });

    document.getElementById('rv-detail-close').addEventListener('click', closeDetail);
    scrim.addEventListener('click', closeDetail);
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && !panel.hidden) {
            closeDetail();
        }
    });

    window.addEventListener('resize', function () {
        map.invalidateSize();
    });
    window.addEventListener('load', fitFrance);
    setTimeout(fitFrance, 60);
    setTimeout(fitFrance, 400);
    load();
}());
