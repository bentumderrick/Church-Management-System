// assets/js/music.js
let currentAudio = null;
let currentHymnId = null;
let currentHymnData = {};
let playlist = [];
let currentIndex = -1;
let isPlaying = false;
let viewMode = 'grid';

window.addEventListener('beforeunload', function() {
    if (currentAudio) {
        currentAudio.pause();
        currentAudio = null;
    }
});

function escapeHtml(text) {
    if (!text) return '';
    return String(text).replace(/[&<>"']/g, function(m) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m];
    });
}

function playHymn(element) {
    const data = element.dataset;
    if (!data.audio || data.audio === '') {
        alert('Audio file not found.');
        return;
    }
    currentHymnData = {
        id: data.id,
        title: data.title,
        number: data.number,
        audio: data.audio,
        lyrics: data.lyrics || 'No lyrics available.',
        cover: data.cover || 'images/default-cover.jpg',
        uploaded: data.uploaded || 'Unknown',
        plays: parseInt(data.plays) || 0,
        downloads: parseInt(data.downloads) || 0
    };
    currentHymnId = data.id;
    buildPlaylist();
    currentIndex = playlist.findIndex(item => item.id == data.id);

    document.getElementById('playerTitle').textContent = data.title + ' (#' + data.number + ')';
    document.getElementById('playerCover').src = data.cover || 'images/default-cover.jpg';
    document.getElementById('playerArtist').textContent = 'Uploaded by: ' + (data.uploaded || 'Unknown');
    showPlayerBar();

    document.getElementById('fsTitle').textContent = data.title;
    document.getElementById('fsArtist').textContent = '#' + data.number + ' – ' + (data.uploaded || 'Unknown');
    document.getElementById('fsCover').src = data.cover || 'images/default-cover.jpg';
    document.getElementById('fsLyrics').innerHTML = escapeHtml(data.lyrics).replace(/\n/g, '<br>');

    if (currentAudio) {
        currentAudio.pause();
        currentAudio = null;
    }

    currentAudio = new Audio(data.audio);
    currentAudio.addEventListener('timeupdate', updateProgress);
    currentAudio.addEventListener('loadedmetadata', function() {
        document.getElementById('totalTime').textContent = formatTime(this.duration);
        document.getElementById('fsTotalTime').textContent = formatTime(this.duration);
    });
    currentAudio.addEventListener('ended', function() {
        isPlaying = false;
        document.getElementById('playPauseBtn').innerHTML = '<i class="fas fa-play"></i>';
        document.getElementById('fsPlayPauseBtn').className = 'fas fa-play';
        nextHymn();
    });

    currentAudio.play().then(() => {
        isPlaying = true;
        document.getElementById('playPauseBtn').innerHTML = '<i class="fas fa-pause"></i>';
        document.getElementById('fsPlayPauseBtn').className = 'fas fa-pause';
        incrementPlayCount(currentHymnId);
    }).catch(() => {
        isPlaying = false;
        document.getElementById('playPauseBtn').innerHTML = '<i class="fas fa-play"></i>';
        document.getElementById('fsPlayPauseBtn').className = 'fas fa-play';
    });
}

function togglePlayPause() {
    if (!currentAudio) return;
    if (isPlaying) {
        currentAudio.pause();
        isPlaying = false;
        document.getElementById('playPauseBtn').innerHTML = '<i class="fas fa-play"></i>';
        document.getElementById('fsPlayPauseBtn').className = 'fas fa-play';
    } else {
        currentAudio.play().then(() => {
            isPlaying = true;
            document.getElementById('playPauseBtn').innerHTML = '<i class="fas fa-pause"></i>';
            document.getElementById('fsPlayPauseBtn').className = 'fas fa-pause';
        }).catch(() => {});
    }
}

function updateProgress() {
    if (!currentAudio) return;
    const progress = (currentAudio.currentTime / currentAudio.duration) * 100;
    document.getElementById('progressFill').style.width = progress + '%';
    document.getElementById('currentTime').textContent = formatTime(currentAudio.currentTime);
    const fsFill = document.getElementById('fsProgressFill');
    if (fsFill) fsFill.style.width = progress + '%';
    const fsCur = document.getElementById('fsCurrentTime');
    if (fsCur) fsCur.textContent = formatTime(currentAudio.currentTime);
}

function seekAudio(event) {
    if (!currentAudio) return;
    const bar = event.currentTarget;
    const rect = bar.getBoundingClientRect();
    const x = (event.clientX - rect.left) / rect.width;
    currentAudio.currentTime = x * currentAudio.duration;
}

function seekAudioFS(event) {
    if (!currentAudio) return;
    const bar = document.getElementById('fsProgressBar');
    const rect = bar.getBoundingClientRect();
    const x = (event.clientX - rect.left) / rect.width;
    currentAudio.currentTime = x * currentAudio.duration;
}

function formatTime(seconds) {
    if (isNaN(seconds)) return '0:00';
    const mins = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);
    return mins + ':' + (secs < 10 ? '0' : '') + secs;
}

function buildPlaylist() {
    const items = document.querySelectorAll('.hymn-card, .hymn-item');
    playlist = [];
    items.forEach(item => {
        const data = item.dataset;
        if (data.audio && data.audio !== '') {
            playlist.push({
                id: data.id,
                title: data.title,
                number: data.number,
                audio: data.audio,
                lyrics: data.lyrics || 'No lyrics available.',
                cover: data.cover || 'images/default-cover.jpg',
                uploaded: data.uploaded || 'Unknown',
                element: item
            });
        }
    });
}

function nextHymn() {
    if (playlist.length === 0) return;
    currentIndex = (currentIndex + 1) % playlist.length;
    const next = playlist[currentIndex];
    if (next && next.element) playHymn(next.element);
}

function previousHymn() {
    if (playlist.length === 0) return;
    currentIndex = (currentIndex - 1 + playlist.length) % playlist.length;
    const prev = playlist[currentIndex];
    if (prev && prev.element) playHymn(prev.element);
}

function togglePlaylist() {
    if (playlist.length === 0) {
        alert('No hymns in playlist.');
        return;
    }
    let message = '📋 Playlist (' + playlist.length + ' hymns):\n\n';
    playlist.forEach((item, index) => {
        message += (index === currentIndex ? '▶ ' : '  ') + '#' + item.number + ' ' + item.title + '\n';
    });
    alert(message);
}

function closePlayer() {
    if (currentAudio) currentAudio.pause();
    isPlaying = false;
    document.getElementById('musicPlayer').classList.remove('active');
    closeFullscreenPlayer();
}

// ---- FULLSCREEN PLAYER ----
// Robust version: works even when the SPA injects this page via innerHTML.
// 1. Styles are injected into <head> from JS (a <style> inside the partial can
//    be wiped or scoped by the shell).
// 2. Stale overlays left in <body> from a previous visit are removed, so
//    getElementById() can never return a dead duplicate.
// 3. Visibility is forced with inline styles, so it shows even if the page's
//    CSS never loaded or an ancestor has a transform (which breaks fixed).
function ensureFsStyles() {
    if (document.getElementById('musicFsStylesHead')) return;
    const s = document.createElement('style');
    s.id = 'musicFsStylesHead';
    s.textContent = `
    .fullscreen-player{display:none;position:fixed;inset:0;width:100vw;height:100vh;
      background:rgba(0,0,0,.95);z-index:2147483000;color:#fff;flex-direction:column;
      justify-content:center;align-items:center;text-align:center}
    .fullscreen-player.active{display:flex}
    .fullscreen-player .close-fs{position:absolute;top:20px;right:30px;font-size:2rem;cursor:pointer;color:#fff}
    .fullscreen-player .cover-large{width:300px;height:300px;border-radius:12px;object-fit:cover;margin-bottom:20px}
    .fullscreen-player .song-title{font-size:2rem;margin:10px 0}
    .fullscreen-player .artist{color:#adb5bd;margin-bottom:20px}
    .fullscreen-player .progress-large{width:80%;max-width:600px;height:6px;background:rgba(255,255,255,.2);border-radius:3px;margin:20px auto;cursor:pointer}
    .fullscreen-player .progress-fill-large{height:100%;background:#ffc107;border-radius:3px;width:0%}
    .fullscreen-player .time-large{display:flex;justify-content:space-between;width:80%;max-width:600px;color:#adb5bd}
    .fullscreen-player .controls-large{display:flex;gap:20px;font-size:2rem;margin:20px 0}
    .fullscreen-player .controls-large i{cursor:pointer}
    .fullscreen-player .lyrics-panel{max-width:600px;max-height:200px;overflow-y:auto;color:#adb5bd;white-space:pre-line;margin-top:20px}`;
    document.head.appendChild(s);
}

function getFsElement() {
    const all = Array.from(document.querySelectorAll('#fullscreenPlayer'));
    if (!all.length) return null;
    // Keep the last one (freshly rendered partial), drop stale duplicates.
    const fs = all[all.length - 1];
    all.slice(0, -1).forEach(el => el.remove());
    return fs;
}

function openFullscreenPlayer() {
    ensureFsStyles();
    const fs = getFsElement();
    if (!fs) {
        alert('Fullscreen player element not found in DOM.');
        return;
    }
    if (fs.parentElement !== document.body) document.body.appendChild(fs);
    fs.classList.add('active');
    fs.style.cssText = 'display:flex;position:fixed;inset:0;width:100vw;height:100vh;z-index:2147483000;';
    document.body.style.overflow = 'hidden';
    if (currentAudio) {
        document.getElementById('fsPlayPauseBtn').className = isPlaying ? 'fas fa-pause' : 'fas fa-play';
        updateProgress();
    }
}

function closeFullscreenPlayer() {
    const fs = document.getElementById('fullscreenPlayer');
    if (fs) {
        fs.classList.remove('active');
        fs.style.display = 'none';
    }
    document.body.style.overflow = '';
}

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeFullscreenPlayer();
});

// Expose globals explicitly (needed if the shell ever loads this as a module).
window.openFullscreenPlayer = openFullscreenPlayer;
window.closeFullscreenPlayer = closeFullscreenPlayer;

function toggleLyrics() {
    if (!currentHymnData.lyrics || currentHymnData.lyrics === 'No lyrics available.') {
        alert('No lyrics available for this hymn.');
        return;
    }
    document.getElementById('lyricsTitle').textContent = currentHymnData.title + ' (#' + currentHymnData.number + ')';
    document.getElementById('lyricsText').innerHTML = escapeHtml(currentHymnData.lyrics).replace(/\n/g, '<br>');
    document.getElementById('lyricsModal').classList.add('active');
}

function closeLyrics() {
    document.getElementById('lyricsModal').classList.remove('active');
}

function downloadCurrentHymn() {
    if (!currentHymnData.audio) {
        alert('No audio file to download.');
        return;
    }
    const link = document.createElement('a');
    link.href = currentHymnData.audio;
    link.download = currentHymnData.title + ' - Hymn ' + currentHymnData.number + '.mp3';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    incrementDownloadCount(currentHymnId);
    const btn = document.getElementById('downloadBtn');
    const orig = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-check" style="color:#2ecc71;"></i>';
    setTimeout(() => btn.innerHTML = orig, 2000);
}

function incrementPlayCount(hymnId) {
    if (!hymnId) return;
    fetch('music-actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=play&id=' + hymnId
    }).catch(() => {});
}

function incrementDownloadCount(hymnId) {
    if (!hymnId) return;
    fetch('music-actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=download&id=' + hymnId
    }).catch(() => {});
}

function setView(view) {
    viewMode = view;
    document.getElementById('hymnsGrid').style.display = view === 'grid' ? 'grid' : 'none';
    document.getElementById('hymnsList').style.display = view === 'list' ? 'block' : 'none';
    document.querySelectorAll('.view-toggle button').forEach((btn, i) => {
        btn.classList.toggle('active', i === (view === 'grid' ? 0 : 1));
    });
}

function filterHymns() {
    const query = document.getElementById('searchInput').value.toLowerCase();
    const sort = document.getElementById('sortSelect').value;
    const cards = document.querySelectorAll('.hymn-card, .hymn-item');
    let visible = [];
    cards.forEach(item => {
        const title = (item.dataset.title || '').toLowerCase();
        const number = (item.dataset.number || '').toLowerCase();
        const match = title.includes(query) || number.includes(query);
        item.style.display = match ? '' : 'none';
        if (match) visible.push(item);
    });
    if (visible.length > 1 && sort !== 'newest') {
        const parent = viewMode === 'grid' ? document.getElementById('hymnsGrid') : document.getElementById('hymnsList');
        visible.sort((a, b) => {
            switch(sort) {
                case 'title': return (a.dataset.title || '').localeCompare(b.dataset.title || '');
                case 'number': return parseInt(a.dataset.number) - parseInt(b.dataset.number);
                case 'popular': return (parseInt(b.dataset.plays) || 0) - (parseInt(a.dataset.plays) || 0);
                case 'oldest': return parseInt(a.dataset.id) - parseInt(b.dataset.id);
                default: return parseInt(b.dataset.id) - parseInt(a.dataset.id);
            }
        });
        visible.forEach(item => parent.appendChild(item));
    }
}

function editHymn(id) {
    const form = document.getElementById('editForm_' + id);
    if (!form) return;
    const title = prompt('Edit Title:', form.edit_title.value);
    if (title === null) return;
    const number = prompt('Edit Hymn Number:', form.edit_hymn_number.value);
    if (number === null) return;
    const lyrics = prompt('Edit Lyrics:', form.edit_lyrics.value || '');
    if (lyrics === null) return;
    form.edit_title.value = title;
    form.edit_hymn_number.value = number;
    form.edit_lyrics.value = lyrics;
    form.submit();
}

document.addEventListener('keydown', function(e) {
    if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
    switch(e.key) {
        case ' ': e.preventDefault(); togglePlayPause(); break;
        case 'ArrowRight': e.preventDefault(); nextHymn(); break;
        case 'ArrowLeft': e.preventDefault(); previousHymn(); break;
        case 'l': case 'L': toggleLyrics(); break;
        case 'Escape': closePlayer(); closeLyrics(); break;
    }
});


// ---- Fallback: make sure the mini player is actually visible ----
// If the shell's stylesheet has no .music-player.active rule (or the partial's
// own <style> got stripped), the bar stays display:none and it looks like the
// player "never comes". Detect that and force it.
function ensurePlayerStyles() {
    if (document.getElementById('musicPlayerFallbackStyles')) return;
    const s = document.createElement('style');
    s.id = 'musicPlayerFallbackStyles';
    s.textContent = `
    #musicPlayer.mp-forced{display:block !important;position:fixed !important;left:0;right:0;bottom:0;
      z-index:2147482000;background:#0c1a2b;border-top:1px solid rgba(255,255,255,.12);color:#fff;padding:10px 16px}
    #musicPlayer.mp-forced .player-inner{display:flex;align-items:center;gap:14px;flex-wrap:wrap}
    #musicPlayer.mp-forced img{width:48px;height:48px;border-radius:8px;object-fit:cover}
    #musicPlayer.mp-forced .song-info{min-width:140px}
    #musicPlayer.mp-forced .song-info .title{font-weight:600}
    #musicPlayer.mp-forced .song-info .artist{font-size:.75rem;color:#adb5bd}
    #musicPlayer.mp-forced .controls,#musicPlayer.mp-forced .right-controls{display:flex;gap:8px;align-items:center}
    #musicPlayer.mp-forced button{background:none;border:none;color:#fff;cursor:pointer;font-size:1rem}
    #musicPlayer.mp-forced .play-btn{background:#ffc107;color:#0c1a2b;border-radius:50%;width:38px;height:38px}
    #musicPlayer.mp-forced .progress-container{display:flex;align-items:center;gap:8px;flex:1;min-width:180px}
    #musicPlayer.mp-forced .progress-bar{flex:1;height:5px;background:rgba(255,255,255,.2);border-radius:3px;cursor:pointer}
    #musicPlayer.mp-forced .progress-fill{height:100%;width:0%;background:#ffc107;border-radius:3px}
    #musicPlayer.mp-forced .time{font-size:.7rem;color:#adb5bd}`;
    document.head.appendChild(s);
}

function showPlayerBar() {
    const all = Array.from(document.querySelectorAll('#musicPlayer'));
    if (!all.length) return null;
    const p = all[all.length - 1];
    all.slice(0, -1).forEach(el => el.remove());   // drop stale copies from previous SPA visits
    p.classList.add('active');
    if (getComputedStyle(p).display === 'none') { // shell CSS has no rule for it
        ensurePlayerStyles();
        p.classList.add('mp-forced');
    }
    return p;
}
window.showPlayerBar = showPlayerBar;

