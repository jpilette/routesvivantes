(function () {
    'use strict';

    var form = document.getElementById('rv-form');
    if (!form) {
        return;
    }

    var steps = Array.prototype.slice.call(document.querySelectorAll('.step'));
    var cursor = 0;
    var photos = { 1: null, 2: null };
    var geoState = 'waiting';
    var geoPending = false;
    var geoFailed = false;
    var permissionState = 'pending';
    var permissionPromise = null;
    var captureIndex = 1;
    var stream = null;
    var sending = false;

    var errorBox = document.getElementById('rv-error');
    var progress = document.getElementById('rv-progress');
    var bar = document.getElementById('rv-bar');
    var geoBox = document.getElementById('rv-geo');
    var geoLabel = document.getElementById('rv-geo-label');
    var geoText = document.getElementById('rv-geo-text');
    var geoHelp = document.getElementById('rv-geo-help');
    var geoButton = document.getElementById('rv-geo-action');
    var latInput = document.getElementById('rv-lat');
    var lngInput = document.getElementById('rv-lng');
    var lieuWait = document.getElementById('rv-lieu-wait');
    var video = document.getElementById('rv-video');
    var camera = document.getElementById('rv-camera');
    var shutter = document.getElementById('rv-shutter');

    function showError(message) {
        errorBox.hidden = false;
        errorBox.textContent = message;
        errorBox.scrollIntoView(true);
    }

    function clearError() {
        errorBox.hidden = true;
        errorBox.textContent = '';
    }

    function hasFix() {
        var lat = parseFloat(latInput.value);
        var lng = parseFloat(lngInput.value);
        if (!isFinite(lat) || !isFinite(lng)) {
            return false;
        }
        if (Math.abs(lat) < 0.0000001 && Math.abs(lng) < 0.0000001) {
            return false;
        }
        return true;
    }

    function clearFix() {
        latInput.value = '';
        lngInput.value = '';
    }

    function shouldShowGeoStep() {
        if (hasFix()) {
            return false;
        }
        if (permissionState === 'pending') {
            return false;
        }
        if (permissionState === 'granted' && !geoFailed) {
            return false;
        }
        return true;
    }

    function route() {
        return steps.filter(function (step) {
            return step.getAttribute('data-part') !== 'lieu' || shouldShowGeoStep();
        });
    }

    function currentStep() {
        var routeSteps = route();
        return routeSteps[cursor] || routeSteps[0];
    }

    function currentPart() {
        var step = currentStep();
        return step ? step.getAttribute('data-part') : '';
    }

    function syncLieuWait() {
        if (!lieuWait) {
            return;
        }
        lieuWait.hidden = !(currentPart() === 'mot' && !hasFix());
    }

    function refreshSubmit() {
        var button = document.getElementById('rv-submit');
        if (!button) {
            return;
        }
        if (sending) {
            button.disabled = true;
            return;
        }
        button.disabled = !hasFix();
    }

    function go(index, shouldFocus) {
        var routeSteps = route();
        if (!routeSteps.length) {
            return;
        }
        if (index < 0) {
            index = 0;
        }
        if (index > routeSteps.length - 1) {
            index = routeSteps.length - 1;
        }
        var active = routeSteps[index];
        if (active.getAttribute('data-part') === 'mot' && !hasFix() && shouldShowGeoStep()) {
            goToPart('lieu', null, shouldFocus);
            showError('Le lieu est obligatoire.');
            return;
        }
        cursor = index;
        steps.forEach(function (step) {
            step.hidden = step !== active;
        });
        progress.textContent = 'Étape ' + (index + 1) + ' sur ' + routeSteps.length;
        bar.style.width = (((index + 1) / routeSteps.length) * 100) + '%';
        clearError();
        if (active.getAttribute('data-part') === 'mot') {
            updateRecap();
        }
        syncLieuWait();
        refreshSubmit();
        if (shouldFocus !== false) {
            var title = active.querySelector('h2');
            if (title) {
                title.focus();
            }
            window.scrollTo(0, 0);
        }
    }

    function goToPart(part, photoIndex, shouldFocus) {
        var routeSteps = route();
        var index = 0;
        var found = false;
        routeSteps.forEach(function (step, stepIndex) {
            if (found) {
                return;
            }
            if (step.getAttribute('data-part') !== part) {
                return;
            }
            if (photoIndex && step.getAttribute('data-photo') !== String(photoIndex)) {
                return;
            }
            index = stepIndex;
            found = true;
        });
        go(index, shouldFocus);
    }

    function stepBy(part, photoIndex) {
        var found = null;
        steps.forEach(function (step) {
            if (found) {
                return;
            }
            if (step.getAttribute('data-part') !== part) {
                return;
            }
            if (photoIndex && step.getAttribute('data-photo') !== String(photoIndex)) {
                return;
            }
            found = step;
        });
        return found;
    }

    function selected(name) {
        var field = form.querySelector('input[name="' + name + '"]:checked');
        return field ? field.value : '';
    }

    function selectedLabel(name) {
        var field = form.querySelector('input[name="' + name + '"]:checked');
        if (!field) {
            return '';
        }
        var title = field.parentNode.querySelector('.choice-title');
        return title ? title.textContent : field.value;
    }

    function updateRecap() {
        var status = selected('status');
        var statusLabel = status === 'mort' ? 'Mort' : (status === 'blessé' ? 'Blessé' : '');
        var photoCount = (photos[1] ? 1 : 0) + (photos[2] ? 1 : 0);
        var photoLabel = photoCount > 1 ? '2 photos' : '1 photo';
        var parts = [statusLabel, selectedLabel('animal_type'), photoLabel, 'lieu noté'];
        document.getElementById('rv-recap').textContent = parts.filter(Boolean).join(' · ');
    }

    function setGeo(state, text) {
        geoState = state;
        geoBox.setAttribute('data-geo', state);
        geoHelp.hidden = state !== 'refused';
        if (state === 'ok') {
            geoLabel.textContent = 'C’est bon';
            geoText.textContent = text || 'Le lieu est bien noté.';
            geoButton.disabled = false;
            geoButton.textContent = 'Continuer';
            refreshSubmit();
            syncLieuWait();
            if (hasFix() && currentPart() === 'lieu') {
                goToPart('animal');
            }
            return;
        }
        if (state === 'refused') {
            geoLabel.textContent = 'Refusé';
            geoText.textContent = text || 'Le lieu est refusé.';
            geoButton.disabled = false;
            geoButton.textContent = 'Réessayer le lieu';
            refreshSubmit();
            syncLieuWait();
            return;
        }
        geoLabel.textContent = 'En attente';
        geoText.textContent = text || 'Nous cherchons le lieu. Patientez.';
        geoButton.disabled = geoPending;
        geoButton.textContent = geoPending ? 'Patientez…' : 'Autoriser le lieu';
        refreshSubmit();
        syncLieuWait();
    }

    function acceptPosition(position) {
        var lat = position.coords.latitude;
        var lng = position.coords.longitude;
        if (!isFinite(lat) || !isFinite(lng) || (Math.abs(lat) < 0.0000001 && Math.abs(lng) < 0.0000001)) {
            return false;
        }
        latInput.value = String(lat);
        lngInput.value = String(lng);
        geoFailed = false;
        permissionState = 'granted';
        geoPending = false;
        setGeo('ok');
        return true;
    }

    function requestGeo(options) {
        var silent = !!(options && options.silent);
        if (!navigator.geolocation) {
            clearFix();
            geoFailed = true;
            geoPending = false;
            permissionState = 'denied';
            setGeo('refused', 'Ce téléphone ne donne pas le lieu.');
            return;
        }
        geoPending = true;
        geoFailed = false;
        if (!silent || currentPart() === 'lieu') {
            setGeo('waiting', 'Nous cherchons le lieu. Patientez.');
        }
        navigator.geolocation.getCurrentPosition(function (position) {
            if (!acceptPosition(position)) {
                clearFix();
                geoFailed = true;
                geoPending = false;
                setGeo('waiting', 'Le lieu n’arrive pas. Allez dehors, puis réessayez.');
                geoButton.disabled = false;
                geoButton.textContent = 'Réessayer le lieu';
            }
        }, function (error) {
            geoPending = false;
            clearFix();
            geoFailed = true;
            if (error && error.code === 1) {
                permissionState = 'denied';
                setGeo('refused', 'Le lieu est refusé.');
                return;
            }
            setGeo('waiting', 'Le lieu n’arrive pas. Allez dehors, puis réessayez.');
            geoButton.disabled = false;
            geoButton.textContent = 'Réessayer le lieu';
        }, {
            enableHighAccuracy: true,
            timeout: 20000,
            maximumAge: 60000
        });
    }

    function ensurePermission() {
        if (permissionPromise) {
            return permissionPromise;
        }
        permissionPromise = new Promise(function (resolve) {
            if (!navigator.permissions || !navigator.permissions.query) {
                permissionState = 'unknown';
                resolve('unknown');
                return;
            }
            navigator.permissions.query({ name: 'geolocation' }).then(function (status) {
                permissionState = status.state;
                if (status.state === 'granted' && !hasFix() && !geoPending) {
                    requestGeo({ silent: true });
                }
                if (currentPart() === 'etat') {
                    go(cursor, false);
                }
                status.onchange = function () {
                    permissionState = status.state;
                    if (status.state === 'granted') {
                        geoFailed = false;
                        if (!hasFix() && !geoPending) {
                            requestGeo({ silent: true });
                        }
                    }
                };
                resolve(status.state);
            }).catch(function () {
                permissionState = 'unknown';
                resolve('unknown');
            });
        });
        return permissionPromise;
    }

    geoButton.addEventListener('click', function () {
        clearError();
        if (hasFix()) {
            goToPart('animal');
            return;
        }
        requestGeo();
    });

    function validate(step) {
        if (!step) {
            return true;
        }
        var part = step.getAttribute('data-part');
        if (part === 'etat' && selected('status') !== 'mort' && selected('status') !== 'blessé') {
            showError('Dites si l’animal est mort ou blessé.');
            return false;
        }
        if (part === 'lieu' && !hasFix()) {
            showError('Le lieu est obligatoire.');
            return false;
        }
        if (part === 'animal' && !selected('animal_type')) {
            showError('Choisissez un animal de la liste.');
            return false;
        }
        if (part === 'photo' && step.getAttribute('data-photo') === '1' && !photos[1]) {
            showError('La photo 1 est obligatoire.');
            return false;
        }
        return true;
    }

    var advancingChoice = false;

    function continueFromEtat() {
        if (advancingChoice) {
            return;
        }
        if (!validate(currentStep())) {
            return;
        }
        advancingChoice = true;
        var nextButton = currentStep().querySelector('[data-next]');
        if (nextButton) {
            nextButton.disabled = true;
        }
        ensurePermission().then(function () {
            if (nextButton) {
                nextButton.disabled = false;
            }
            advancingChoice = false;
            if (currentPart() !== 'etat') {
                return;
            }
            if (shouldShowGeoStep()) {
                if (permissionState === 'denied') {
                    setGeo('refused', 'Le lieu est refusé.');
                }
                goToPart('lieu');
                return;
            }
            goToPart('animal');
        }).catch(function () {
            advancingChoice = false;
            if (nextButton) {
                nextButton.disabled = false;
            }
        });
    }

    function continueFromAnimal() {
        if (advancingChoice || currentPart() !== 'animal') {
            return;
        }
        if (!validate(currentStep())) {
            return;
        }
        go(cursor + 1);
    }

    form.addEventListener('change', function (event) {
        var input = event.target;
        if (!input || input.type !== 'radio') {
            return;
        }
        if (input.name === 'status' && currentPart() === 'etat') {
            continueFromEtat();
        } else if (input.name === 'animal_type') {
            continueFromAnimal();
        }
    });

    form.addEventListener('click', function (event) {
        if (!event.target.closest) {
            return;
        }
        var choice = event.target.closest('.choice');
        if (choice) {
            var input = choice.querySelector('input[type="radio"]');
            if (input && input.name === 'status' && currentPart() === 'etat') {
                input.checked = true;
                continueFromEtat();
                return;
            }
            if (input && input.name === 'animal_type' && currentPart() === 'animal') {
                input.checked = true;
                continueFromAnimal();
                return;
            }
        }
        var next = event.target.closest('[data-next]');
        var back = event.target.closest('[data-back]');
        if (next) {
            if (currentPart() === 'etat') {
                continueFromEtat();
                return;
            }
            if (validate(currentStep())) {
                go(cursor + 1);
            }
        } else if (back && cursor > 0) {
            go(cursor - 1);
        }
    });

    function stopStream() {
        if (stream) {
            stream.getTracks().forEach(function (track) {
                track.stop();
            });
            stream = null;
        }
        video.srcObject = null;
    }

    function closeCamera() {
        stopStream();
        camera.hidden = true;
    }

    function showFallback(index, refused) {
        var button = document.querySelector('[data-fallback="' + index + '"]');
        if (button) {
            button.hidden = false;
        }
        var help = document.querySelector('[data-photo="' + index + '"] [data-camera-help]');
        if (help) {
            help.hidden = !refused;
        }
    }

    function storePhoto(index, blob) {
        if (photos[index] && photos[index].url) {
            URL.revokeObjectURL(photos[index].url);
        }
        var url = URL.createObjectURL(blob);
        photos[index] = { blob: blob, url: url };
        var box = document.querySelector('[data-preview="' + index + '"]');
        box.querySelector('img').src = url;
        box.hidden = false;
        var choices = document.querySelector('[data-photo-choices="' + index + '"]');
        if (choices) {
            choices.hidden = true;
        }
        var fallback = document.querySelector('[data-fallback="' + index + '"]');
        if (fallback) {
            fallback.hidden = true;
        }
        var help = document.querySelector('[data-photo="' + index + '"] [data-camera-help]');
        if (help) {
            help.hidden = true;
        }
        clearError();
    }

    function blobFromImage(image) {
        var maxEdge = 1600;
        var scale = Math.min(1, maxEdge / Math.max(image.width, image.height));
        var canvas = document.createElement('canvas');
        canvas.width = Math.max(1, Math.round(image.width * scale));
        canvas.height = Math.max(1, Math.round(image.height * scale));
        canvas.getContext('2d').drawImage(image, 0, 0, canvas.width, canvas.height);
        return new Promise(function (resolve) {
            canvas.toBlob(function (blob) {
                resolve(blob);
            }, 'image/jpeg', 0.85);
        });
    }

    function openCamera(index) {
        captureIndex = index;
        clearError();
        if (!window.isSecureContext) {
            showError('La photo en direct a besoin d’une adresse https.');
            showFallback(index, false);
            return;
        }
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            showError('Cet appareil n’ouvre pas la caméra ici.');
            showFallback(index, false);
            return;
        }
        shutter.disabled = true;
        navigator.mediaDevices.getUserMedia({
            audio: false,
            video: {
                facingMode: { ideal: 'environment' },
                width: { ideal: 1600 },
                height: { ideal: 1200 }
            }
        }).then(function (nextStream) {
            stream = nextStream;
            video.srcObject = nextStream;
            return video.play();
        }).then(function () {
            camera.hidden = false;
            shutter.disabled = false;
        }).catch(function (error) {
            stopStream();
            var refused = error && (error.name === 'NotAllowedError' || error.name === 'PermissionDeniedError');
            if (refused) {
                showError('L’appareil photo est refusé.');
            } else {
                showError('L’appareil photo ne s’ouvre pas. Réessayez.');
            }
            showFallback(index, refused);
        });
    }

    document.querySelectorAll('[data-open-camera]').forEach(function (button) {
        button.addEventListener('click', function () {
            openCamera(button.getAttribute('data-open-camera'));
        });
    });

    document.querySelectorAll('[data-retake]').forEach(function (button) {
        button.addEventListener('click', function () {
            var index = button.getAttribute('data-retake');
            var choices = document.querySelector('[data-photo-choices="' + index + '"]');
            if (choices) {
                choices.hidden = false;
            }
        });
    });

    document.querySelectorAll('[data-pick]').forEach(function (button) {
        button.addEventListener('click', function () {
            var index = button.getAttribute('data-pick');
            var input = document.querySelector('[data-file="' + index + '"]');
            if (input) {
                input.click();
            }
        });
    });

    document.querySelectorAll('[data-fallback]').forEach(function (button) {
        button.addEventListener('click', function () {
            var index = button.getAttribute('data-fallback');
            var input = document.querySelector('[data-capture="' + index + '"]');
            if (input) {
                input.click();
            }
        });
    });

    document.querySelectorAll('[data-file], [data-capture]').forEach(function (input) {
        input.addEventListener('change', function () {
            var file = input.files && input.files[0];
            input.value = '';
            if (!file) {
                return;
            }
            if (file.type && file.type.indexOf('image/') !== 0) {
                showError('Choisissez une image.');
                return;
            }
            var index = input.getAttribute('data-file') || input.getAttribute('data-capture');
            var url = URL.createObjectURL(file);
            var image = new Image();
            image.onload = function () {
                URL.revokeObjectURL(url);
                blobFromImage(image).then(function (blob) {
                    if (!blob) {
                        showError('Cette photo n’est pas lisible. Reprenez-la.');
                        return;
                    }
                    storePhoto(index, blob);
                });
            };
            image.onerror = function () {
                URL.revokeObjectURL(url);
                showError('Cette photo n’est pas lisible. Choisissez-en une autre.');
            };
            image.src = url;
        });
    });

    shutter.addEventListener('click', function () {
        var width = video.videoWidth;
        var height = video.videoHeight;
        if (!width || !height) {
            showError('La photo n’a pas été prise. Réessayez.');
            return;
        }
        var maxEdge = 1600;
        var scale = Math.min(1, maxEdge / Math.max(width, height));
        var canvas = document.createElement('canvas');
        canvas.width = Math.max(1, Math.round(width * scale));
        canvas.height = Math.max(1, Math.round(height * scale));
        canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
        canvas.toBlob(function (blob) {
            if (!blob) {
                showError('La photo n’a pas été prise. Réessayez.');
                return;
            }
            storePhoto(captureIndex, blob);
            closeCamera();
        }, 'image/jpeg', 0.85);
    });

    document.getElementById('rv-camera-cancel').addEventListener('click', closeCamera);
    window.addEventListener('pagehide', stopStream);

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        if (sending) {
            return;
        }
        if (currentPart() !== 'mot') {
            if (currentPart() === 'etat') {
                continueFromEtat();
                return;
            }
            if (validate(currentStep())) {
                go(cursor + 1);
            }
            return;
        }
        var checks = [
            ['etat'],
            ['animal'],
            ['photo', '1']
        ];
        var index;
        for (index = 0; index < checks.length; index += 1) {
            var step = stepBy(checks[index][0], checks[index][1]);
            if (!validate(step)) {
                goToPart(checks[index][0], checks[index][1]);
                validate(step);
                return;
            }
        }
        if (!hasFix()) {
            if (permissionState === 'granted' && !geoFailed) {
                showError('Nous cherchons le lieu. Patientez.');
                syncLieuWait();
                return;
            }
            goToPart('lieu');
            showError('Le lieu est obligatoire.');
            return;
        }
        var remark = document.getElementById('rv-remark').value || '';
        if (remark.length > 500) {
            showError('Le mot est trop long. 500 signes maximum.');
            return;
        }

        sending = true;
        var button = document.getElementById('rv-submit');
        button.disabled = true;
        button.textContent = 'Envoi en cours…';
        clearError();

        var body = new FormData();
        body.append('action', 'rv_decl_submit');
        body.append('nonce', form.querySelector('[name="nonce"]').value);
        body.append('status', selected('status'));
        body.append('animal_type', selected('animal_type'));
        body.append('remark', remark);
        body.append('lat', latInput.value);
        body.append('lng', lngInput.value);
        body.append('photo_1', photos[1].blob, 'photo-1.jpg');
        if (photos[2] && photos[2].blob) {
            body.append('photo_2', photos[2].blob, 'photo-2.jpg');
        }

        fetch(document.body.getAttribute('data-ajax'), {
            method: 'POST',
            body: body,
            credentials: 'same-origin'
        }).then(function (response) {
            return response.json().catch(function () {
                return null;
            }).then(function (data) {
                return { data: data };
            });
        }).then(function (result) {
            if (!result.data || result.data.ok !== true) {
                var message = result.data && result.data.message
                    ? result.data.message
                    : 'L’envoi n’a pas marché. Réessayez.';
                showError(message);
                sending = false;
                button.textContent = 'Envoyer le signalement';
                refreshSubmit();
                return;
            }
            stopStream();
            form.hidden = true;
            document.getElementById('rv-progress-wrap').hidden = true;
            document.querySelector('.fine').hidden = true;
            clearError();
            var success = document.getElementById('rv-success');
            success.hidden = false;
            document.getElementById('rv-success-title').focus();
        }).catch(function () {
            showError('L’envoi n’a pas marché. Vérifiez la connexion, puis réessayez.');
            sending = false;
            button.textContent = 'Envoyer le signalement';
            refreshSubmit();
        });
    });

    document.getElementById('rv-again').addEventListener('click', function () {
        window.location.reload();
    });

    var start = document.getElementById('rv-start');
    var progressWrap = document.getElementById('rv-progress-wrap');
    var fine = document.querySelector('.fine');
    document.getElementById('rv-start-btn').addEventListener('click', function () {
        start.hidden = true;
        form.hidden = false;
        progressWrap.hidden = false;
        if (fine) {
            fine.hidden = false;
        }
        ensurePermission();
        go(0);
    });
}());
