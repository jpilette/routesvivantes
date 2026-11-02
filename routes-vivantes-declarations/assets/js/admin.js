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
    var markers = {};
    var itemsById = {};
    var fitPasses = 0;
    var moveMap = null;
    var moveMarker = null;

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

    function moveIcon() {
        return L.divIcon({
            className: 'pin-wrap',
            html: '<span class="move-pin"></span>',
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
        caption.textContent = slot === 2 ? 'Pas de photo 2.' : 'Photo 1 manquante.';
    }

    function setMoveStatus(text, kind) {
        var status = document.getElementById('rv-move-status');
        if (!status) {
            return;
        }
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

    function showSavedCoords(lat, lng) {
        document.getElementById('rv-detail-lat').textContent = formatCoord(lat);
        document.getElementById('rv-detail-lng').textContent = formatCoord(lng);
    }

    function ensureMoveMap() {
        if (moveMap) {
            return;
        }
        var node = document.getElementById('rv-move-map');
        moveMap = L.map(node, {
            scrollWheelZoom: false,
            zoomControl: true
        });
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
        }).addTo(moveMap);
        moveMarker = L.marker([46.6, 2.5], {
            draggable: true,
            keyboard: true,
            title: 'Lieu du signalement',
            icon: moveIcon()
        }).addTo(moveMap);
        moveMarker.on('drag', function () {
            var point = moveMarker.getLatLng();
            showSavedCoords(point.lat, point.lng);
            setMoveStatus('', '');
        });
    }

    function placeMoveMap(lat, lng) {
        ensureMoveMap();
        moveMarker.setLatLng([lat, lng]);
        moveMap.invalidateSize();
        moveMap.setView([lat, lng], 16, { animate: false });
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
        panel.setAttribute('data-id', String(item.id));
        resetDeletePrompt();
        showSavedCoords(item.lat, item.lng);
        setMoveStatus('', '');
        var save = document.getElementById('rv-move-save');
        if (save) {
            save.disabled = false;
            save.textContent = 'Enregistrer ce lieu';
        }
        showPhoto(1, item.photo_1 || '');
        showPhoto(2, item.photo_2 || '');
        scrim.hidden = false;
        panel.hidden = false;
        var lat = Number(item.lat);
        var lng = Number(item.lng);
        window.requestAnimationFrame(function () {
            if (!isFinite(lat) || !isFinite(lng)) {
                return;
            }
            placeMoveMap(lat, lng);
        });
        window.setTimeout(function () {
            if (moveMap && moveMarker && !panel.hidden) {
                moveMap.invalidateSize();
                moveMap.setView(moveMarker.getLatLng(), moveMap.getZoom() || 16, { animate: false });
            }
        }, 280);
        document.getElementById('rv-detail-close').focus();
    }

    function resetDeletePrompt() {
        var ask = document.getElementById('rv-delete-ask');
        var confirmBox = document.getElementById('rv-delete-confirm');
        var error = document.getElementById('rv-delete-error');
        if (ask) {
            ask.hidden = false;
            ask.disabled = false;
        }
        if (confirmBox) {
            confirmBox.hidden = true;
        }
        if (error) {
            error.hidden = true;
            error.textContent = '';
        }
        var yes = document.getElementById('rv-delete-yes');
        if (yes) {
            yes.disabled = false;
            yes.textContent = 'Oui, supprimez-le';
        }
    }

    function removeMarker(id) {
        var key = String(id);
        if (!markers[key]) {
            return;
        }
        layer.removeLayer(markers[key]);
        delete markers[key];
        setCount(countLabel(Object.keys(markers).length));
    }

    function draw(items) {
        layer.clearLayers();
        markers = {};
        itemsById = {};
        closeQuiet();
        var shown = 0;
        items.forEach(function (item) {
            var lat = Number(item.lat);
            var lng = Number(item.lng);
            if (!isFinite(lat) || !isFinite(lng)) {
                return;
            }
            var key = String(item.id);
            itemsById[key] = item;
            var title = (item.animal_label || 'Animal') + ', ' + (item.status_label || '');
            var marker = L.marker([lat, lng], {
                icon: iconFor(item.status),
                keyboard: true,
                title: title
            });
            marker.on('click', function () {
                openDetail(itemsById[key]);
            });
            marker.addTo(layer);
            markers[key] = marker;
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

    document.getElementById('rv-move-save').addEventListener('click', function () {
        var id = panel.getAttribute('data-id');
        var save = document.getElementById('rv-move-save');
        if (!id || !moveMarker) {
            return;
        }
        var point = moveMarker.getLatLng();
        save.disabled = true;
        save.textContent = 'Enregistrement…';
        setMoveStatus('', '');
        var body = new URLSearchParams();
        body.set('action', 'rv_decl_move');
        body.set('nonce', document.body.getAttribute('data-move-nonce') || '');
        body.set('id', id);
        body.set('lat', point.lat.toFixed(7));
        body.set('lng', point.lng.toFixed(7));
        fetch(document.body.getAttribute('data-ajax'), {
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
                setMoveStatus(
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
            if (itemsById[id]) {
                itemsById[id].lat = lat;
                itemsById[id].lng = lng;
            }
            if (markers[id]) {
                markers[id].setLatLng([lat, lng]);
            }
            if (panel.getAttribute('data-id') === id && !panel.hidden) {
                moveMarker.setLatLng([lat, lng]);
                showSavedCoords(lat, lng);
                setMoveStatus('Le lieu est enregistré.', 'is-ok');
            }
        }).catch(function () {
            save.disabled = false;
            save.textContent = 'Enregistrer ce lieu';
            setMoveStatus('Le lieu n’a pas été enregistré. Vérifiez la connexion, puis réessayez.', 'is-error');
        });
    });

    document.getElementById('rv-delete-ask').addEventListener('click', function () {
        document.getElementById('rv-delete-ask').hidden = true;
        document.getElementById('rv-delete-confirm').hidden = false;
        document.getElementById('rv-delete-yes').focus();
    });

    document.getElementById('rv-delete-no').addEventListener('click', function () {
        resetDeletePrompt();
        document.getElementById('rv-delete-ask').focus();
    });

    document.getElementById('rv-delete-yes').addEventListener('click', function () {
        var id = panel.getAttribute('data-id');
        var yes = document.getElementById('rv-delete-yes');
        var error = document.getElementById('rv-delete-error');
        if (!id) {
            return;
        }
        yes.disabled = true;
        yes.textContent = 'Suppression…';
        error.hidden = true;
        var body = new URLSearchParams();
        body.set('action', 'rv_decl_delete');
        body.set('nonce', document.body.getAttribute('data-nonce') || '');
        body.set('id', id);
        fetch(document.body.getAttribute('data-ajax'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: body.toString()
        }).then(function (response) {
            return response.json().catch(function () {
                return null;
            });
        }).then(function (data) {
            if (!data || data.ok !== true) {
                yes.disabled = false;
                yes.textContent = 'Oui, supprimez-le';
                error.hidden = false;
                error.textContent = data && data.message
                    ? data.message
                    : 'La suppression n’a pas marché. Réessayez.';
                return;
            }
            removeMarker(id);
            closeDetail();
        }).catch(function () {
            yes.disabled = false;
            yes.textContent = 'Oui, supprimez-le';
            error.hidden = false;
            error.textContent = 'La suppression n’a pas marché. Vérifiez la connexion, puis réessayez.';
        });
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
        if (moveMap && !panel.hidden) {
            moveMap.invalidateSize();
        }
    });
    window.addEventListener('load', fitFrance);
    setTimeout(fitFrance, 60);
    setTimeout(fitFrance, 400);
    load();
}());
