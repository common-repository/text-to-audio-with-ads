document.addEventListener('DOMContentLoaded', function() {
    if (typeof window.Vocalize !== 'undefined') {
        window.Vocalize.init({
            containerId: 'vocalize-container',
            identifier: textToAudioData.identifier,
            auth: {
                "X-Api-Publisher-Id": textToAudioData.api_key,
                "X-Api-Publisher-Secret": textToAudioData.api_secret
            }
        });

        document.addEventListener('pagehide', window.Vocalize.unload);
        document.addEventListener('unload', window.Vocalize.unload);
    }
});
