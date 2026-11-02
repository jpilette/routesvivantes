(function () {
    'use strict';

    var form = document.getElementById('rv-form');
    if (!form) {
        return;
    }

    var steps = Array.prototype.slice.call(document.querySelectorAll('.step'));
    var current = 0;
    var photos = { 1: null, 2: null };
    var geoState = 'waiting';
    var geoPending = false;
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

    function go(index, shouldFocus) {
        current = index;
        steps.forEach(function (step, stepIndex) {
            step.hidden = stepIndex !== index;
        });
        progress.textContent = 'Étape ' + (index + 1) + ' sur ' + steps.length;
        bar.style.width = (((index + 1) / steps.length) * 100) + '%';
        clearError();
        if (index === steps.length - 1) {
            updateRecap();
        }
        if (shouldFocus !== false) {
            var title = steps[index].querySelector('h2');
            if (title) {
                title.focus();
            }
            window.scrollTo(0, 0);
        }
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
        var parts = [statusLabel, selectedLabel('animal_type'), '2 photos', 'lieu noté'];
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
            return;
        }
        if (state === 'refused') {
            geoLabel.textContent = 'Refusé';
            geoText.textContent = text || 'Le lieu est refusé.';
            geoButton.disabled = false;
            geoButton.textContent = 'Réessayer le lieu';
            return;
        }
        geoLabel.textContent = 'En attente';
        geoText.textContent = text || 'Nous cherchons le lieu. Patientez.';
        geoButton.disabled = geoPending;
        geoButton.textContent = geoPending ? 'Patientez…' : 'Autoriser le lieu';
    }

    function requestGeo() {
        if (!navigator.geolocation) {
            latInput.value = '';
            lngInput.value = '';
            setGeo('refused', 'Ce téléphone ne donne pas le lieu.');
            return;
        }
        geoPending = true;
        setGeo('waiting', 'Nous cherchons le lieu. Patientez.');
        navigator.geolocation.getCurrentPosition(function (position) {
            geoPending = false;
            latInput.value = String(position.coords.latitude);
            lngInput.value = String(position.coords.longitude);
            setGeo('ok');
        }, function (error) {
            geoPending = false;
            latInput.value = '';
            lngInput.value = '';
            if (error && error.code === 1) {
                setGeo('refused', 'Le lieu est refusé.');
                return;
            }
            setGeo('waiting', 'Le lieu n’arrive pas. Allez dehors, puis réessayez.');
            geoButton.disabled = false;
            geoButton.textContent = 'Réessayer le lieu';
        }, {
            enableHighAccuracy: true,
            timeout: 20000,
            maximumAge: 10000
        });
    }

    geoButton.addEventListener('click', function () {
        clearError();
        if (geoState === 'ok' && latInput.value && lngInput.value) {
            go(1);
            return;
        }
        requestGeo();
    });

    function validate(index) {
        if (index === 0 && (geoState !== 'ok' || !latInput.value || !lngInput.value)) {
            showError('Le lieu est obligatoire.');
            return false;
        }
        if (index === 1 && (selected('status') !== 'mort' && selected('status') !== 'blessé')) {
            showError('Dites si l’animal est mort ou blessé.');
            return false;
        }
        if (index === 2 && !selected('animal_type')) {
            showError('Choisissez un animal de la liste.');
            return false;
        }
        if (index === 3 && !photos[1]) {
            showError('La photo 1 est obligatoire.');
            return false;
        }
        if (index === 4 && !photos[2]) {
            showError('La photo 2 est obligatoire.');
            return false;
        }
        return true;
    }

    form.addEventListener('click', function (event) {
        if (!event.target.closest) {
            return;
        }
        var next = event.target.closest('[data-next]');
        var back = event.target.closest('[data-back]');
        if (next) {
            if (validate(current)) {
                go(current + 1);
            }
        } else if (back && current > 0) {
            go(current - 1);
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
        document.querySelector('[data-open-camera="' + index + '"]').hidden = true;
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
            openCamera(button.getAttribute('data-retake'));
        });
    });

    document.querySelectorAll('[data-fallback]').forEach(function (button) {
        button.addEventListener('click', function () {
            var index = button.getAttribute('data-fallback');
            var input = document.querySelector('[data-file="' + index + '"]');
            if (input) {
                input.click();
            }
        });
    });

    document.querySelectorAll('[data-file]').forEach(function (input) {
        input.addEventListener('change', function () {
            var file = input.files && input.files[0];
            input.value = '';
            if (!file) {
                return;
            }
            var index = input.getAttribute('data-file');
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
                showError('Cette photo n’est pas lisible. Reprenez-la avec l’appareil photo.');
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
        if (current !== steps.length - 1) {
            if (validate(current)) {
                go(current + 1);
            }
            return;
        }
        var index;
        for (index = 0; index <= 4; index += 1) {
            if (!validate(index)) {
                go(index);
                validate(index);
                return;
            }
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
        body.append('photo_2', photos[2].blob, 'photo-2.jpg');

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
                button.disabled = false;
                button.textContent = 'Envoyer le signalement';
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
            button.disabled = false;
            button.textContent = 'Envoyer le signalement';
        });
    });

    document.getElementById('rv-again').addEventListener('click', function () {
        window.location.reload();
    });

    go(0, false);
    setGeo('waiting', 'Le lieu n’est pas encore demandé.');
}());
