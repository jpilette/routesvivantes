(function () {
    'use strict';

    var root = document.getElementById('rv-lieux');
    var editor = document.getElementById('rv-editor');
    var mapNode = document.getElementById('rv-move-map');
    if (!root || !editor || !mapNode) {
        return;
    }
    if (typeof L === 'undefined') {
        editor.hidden = false;
        document.getElementById('rv-editor-help').textContent = 'La carte ne s’affiche pas. Rechargez la page.';
        return;
    }

    var map = null;
    var marker = null;
    var currentButton = null;

    function formatCoord(value) {
        var number = Number(value);
        if (!isFinite(number)) {
            return '—';
        }
        return number.toFixed(5).replace('.', ',');
    }

    function setStatus(text, kind) {
        var status = document.getElementById('rv-move-status');
        status.classList.remove('is-ok', 'is-error');
        if (!text) {
            status.hidden = true;
            status.textContent = '';
            return;
        }
        status.hidden = false;
        status.textContent = text;
        if (kind) {
            status.classList.add(kind);
        }
    }

    function moveIcon() {
        return L.divIcon({
            className: 'pin-wrap',
            html: '<span class="move-pin"></span>',
            iconSize: [36, 36],
            iconAnchor: [18, 18]
        });
    }

    function ensureMap() {
        if (map) {
            return;
        }
        map = L.map(mapNode, {
            scrollWheelZoom: false,
            zoomControl: true
        });
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
        }).addTo(map);
        marker = L.marker([46.6, 2.5], {
            draggable: true,
            keyboard: true,
            title: 'Lieu du signalement',
            icon: moveIcon()
        }).addTo(map);
        marker.on('drag', function () {
            var point = marker.getLatLng();
            document.getElementById('rv-editor-lat').textContent = formatCoord(point.lat);
            document.getElementById('rv-editor-lng').textContent = formatCoord(point.lng);
            setStatus('', '');
        });
    }

    function showPlace(button) {
        var lat = Number(button.getAttribute('data-lat'));
        var lng = Number(button.getAttribute('data-lng'));
        if (!isFinite(lat) || !isFinite(lng)) {
            return;
        }
        currentButton = button;
        document.querySelectorAll('#rv-lieux tr.is-selected').forEach(function (row) {
            row.classList.remove('is-selected');
        });
        var row = button.closest('tr');
        if (row) {
            row.classList.add('is-selected');
        }
        var label = button.getAttribute('data-label') || 'ce signalement';
        document.getElementById('rv-editor-title').textContent = 'Déplacez le lieu — ' + label;
        document.getElementById('rv-editor-lat').textContent = formatCoord(lat);
        document.getElementById('rv-editor-lng').textContent = formatCoord(lng);
        setStatus('', '');
        var save = document.getElementById('rv-move-save');
        save.disabled = false;
        save.textContent = 'Enregistrer ce lieu';
        editor.hidden = false;
        ensureMap();
        marker.setLatLng([lat, lng]);
        map.invalidateSize();
        map.setView([lat, lng], 16, { animate: false });
        window.setTimeout(function () {
            if (!map || editor.hidden) {
                return;
            }
            map.invalidateSize();
            map.setView(marker.getLatLng(), map.getZoom() || 16, { animate: false });
        }, 80);
        editor.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    root.addEventListener('click', function (event) {
        var button = event.target.closest('.rv-pick');
        if (!button || !root.contains(button)) {
            return;
        }
        showPlace(button);
    });

    document.getElementById('rv-move-save').addEventListener('click', function () {
        if (!currentButton || !marker) {
            return;
        }
        var id = currentButton.getAttribute('data-id');
        var point = marker.getLatLng();
        var save = document.getElementById('rv-move-save');
        var button = currentButton;
        save.disabled = true;
        save.textContent = 'Enregistrement…';
        setStatus('', '');
        var body = new URLSearchParams();
        body.set('action', 'rv_decl_move');
        body.set('nonce', root.getAttribute('data-nonce') || '');
        body.set('id', id);
        body.set('lat', point.lat.toFixed(7));
        body.set('lng', point.lng.toFixed(7));
        fetch(root.getAttribute('data-ajax'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: body.toString()
        }).then(function (response) {
            return response.json().catch(function () {
                return null;
            });
        }).then(function (data) {
            save.disabled = false;
            save.textContent = 'Enregistrer ce lieu';
            if (!data || data.ok !== true) {
                setStatus(
                    data && data.message ? data.message : 'Le lieu n’a pas été enregistré. Réessayez.',
                    'is-error'
                );
                return;
            }
            var lat = Number(data.lat);
            var lng = Number(data.lng);
            if (!isFinite(lat) || !isFinite(lng)) {
                lat = point.lat;
                lng = point.lng;
            }
            button.setAttribute('data-lat', lat.toFixed(7));
            button.setAttribute('data-lng', lng.toFixed(7));
            var row = document.getElementById('rv-row-' + id);
            if (row) {
                row.querySelector('.rv-lat').textContent = formatCoord(lat);
                row.querySelector('.rv-lng').textContent = formatCoord(lng);
            }
            if (currentButton === button) {
                marker.setLatLng([lat, lng]);
                document.getElementById('rv-editor-lat').textContent = formatCoord(lat);
                document.getElementById('rv-editor-lng').textContent = formatCoord(lng);
                setStatus('Le lieu est enregistré.', 'is-ok');
            }
        }).catch(function () {
            save.disabled = false;
            save.textContent = 'Enregistrer ce lieu';
            setStatus('Le lieu n’a pas été enregistré. Vérifiez la connexion, puis réessayez.', 'is-error');
        });
    });

    window.addEventListener('resize', function () {
        if (map && !editor.hidden) {
            map.invalidateSize();
        }
    });
}());
