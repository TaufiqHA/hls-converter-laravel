<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>{{ $video['title'] }}</title>
  <!-- Favicon -->
  <link rel="icon" type="image/x-icon" href="{{ config('app.url') }}/favicon.ico">
  <link rel="shortcut icon" href="{{ config('app.url') }}/favicon.ico">

  <script>
    // Helper function to safely encode JSON for script tags
    function safeJSON(obj) {
      return JSON.stringify(obj || {})
        .replace(/</g, '\\u003c')
        .replace(/>/g, '\\u003e')
        .replace(/&/g, '\\u0026');
    }
  </script>

  <!-- Player CSS -->
  @php
    $selectedPlayer = (isset($playerSettings) && isset($playerSettings['player'])) ? $playerSettings['player'] : 'videojs';
  @endphp

  {{-- Load CSS for the configured player --}}
  @if($selectedPlayer === 'videojs')
    <link rel="stylesheet" href="https://vjs.zencdn.net/8.6.1/video-js.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/videojs-hls-quality-selector@2.0.0/dist/videojs-hls-quality-selector.css" />
  @elseif($selectedPlayer === 'dplayer')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/dplayer@1.27.1/dist/DPlayer.min.css" />
  @endif
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    html, body {
      width: 100%;
      height: 100%;
      overflow: hidden;
      background: #000;
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
      position: fixed;
      top: 0;
      left: 0;
    }

    .video-wrapper {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: #000;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
    }

    /* Ensure video container fills wrapper */
    .video-wrapper > div,
    .video-wrapper > video {
      width: 100%;
      height: 100%;
    }

    .plyr {
      width: 100%;
      height: 100%;
      max-width: 100%;
      max-height: 100%;
    }

    .plyr--video {
      height: 100%;
    }

    .plyr__video-wrapper {
      height: 100%;
    }

    video {
      width: 100%;
      height: 100%;
      object-fit: contain;
    }

    /* VideoJS Styles */
    .video-js {
      width: 100% !important;
      height: 100% !important;
      position: absolute !important;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      background-color: #000 !important;
    }

    .video-js .vjs-tech {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      object-fit: contain;
    }

    /* Override VideoJS fluid mode dimensions */
    .video-js.vjs-fluid,
    .video-js.vjs-16-9,
    .video-js.vjs-4-3,
    .video-js.vjs-fill {
      padding-top: 0 !important;
      height: 100% !important;
      width: 100% !important;
    }

    /* Fix poster display */
    .video-js .vjs-poster {
      background-size: contain !important;
      background-position: center center !important;
      background-color: #000 !important;
    }

    /* Hide VideoJS error display initially */
    .video-js .vjs-error-display {
      display: none;
    }

    .video-js.vjs-error .vjs-error-display {
      display: flex;
    }

    /* Big Play Button - Centered */
    .video-js .vjs-big-play-button {
      position: absolute !important;
      background: rgba(0, 0, 0, 0.7) !important;
      border: 2px solid rgba(255, 255, 255, 0.9) !important;
      border-radius: 50% !important;
      width: 70px !important;
      height: 70px !important;
      top: 0 !important;
      left: 0 !important;
      right: 0 !important;
      bottom: 0 !important;
      margin: auto !important;
      transition: all 0.2s ease;
    }

    .video-js .vjs-big-play-button:hover {
      background: rgba(255, 0, 0, 0.8) !important;
      border-color: #ff0000 !important;
      transform: scale(1.1);
    }

    .video-js .vjs-big-play-button .vjs-icon-placeholder:before {
      font-size: 36px;
      line-height: 66px;
      display: flex;
      align-items: center;
      justify-content: center;
      height: 100%;
    }

    /* Control Bar - Compact & Simple */
    .video-js .vjs-control-bar {
      background: linear-gradient(transparent, rgba(0, 0, 0, 0.9));
      height: 36px;
      padding: 0 6px;
      display: flex;
      align-items: center;
    }

    /* All control buttons - Compact sizing */
    .video-js .vjs-control {
      width: 28px;
      height: 36px;
    }

    .video-js .vjs-button > .vjs-icon-placeholder:before {
      font-size: 16px;
      line-height: 36px;
    }

    /* Play/Pause button */
    .video-js .vjs-play-control {
      width: 32px;
    }

    .video-js .vjs-play-control .vjs-icon-placeholder:before {
      font-size: 18px;
    }

    /* Time display - Compact inline */
    .video-js .vjs-time-control {
      font-size: 11px;
      line-height: 36px;
      padding: 0 3px;
      min-width: auto;
    }

    .video-js .vjs-current-time {
      display: block;
      padding-left: 6px;
    }

    .video-js .vjs-duration {
      display: block;
      padding-right: 6px;
    }

    .video-js .vjs-time-divider {
      padding: 0 1px;
      min-width: 10px;
      font-size: 11px;
      line-height: 36px;
    }

    /* Hide remaining time (redundant with duration) */
    .video-js .vjs-remaining-time {
      display: none !important;
    }

    /* Progress bar */
    .video-js .vjs-progress-control {
      flex: 1;
      height: 36px;
    }

    .video-js .vjs-progress-holder {
      height: 3px;
      margin: 0 6px;
      border-radius: 2px;
    }

    .video-js .vjs-progress-holder:hover {
      height: 5px;
    }

    .video-js .vjs-play-progress {
      background: #ff0000;
      border-radius: 2px;
    }

    .video-js .vjs-play-progress:before {
      font-size: 9px;
      top: -3px;
      color: #ff0000;
    }

    .video-js .vjs-load-progress {
      background: rgba(255, 255, 255, 0.25);
      border-radius: 2px;
    }

    .video-js .vjs-slider-bar {
      background: rgba(255, 255, 255, 0.4);
    }

    /* Volume control - Compact */
    .video-js .vjs-volume-panel {
      width: 28px;
      transition: width 0.2s;
    }

    .video-js .vjs-volume-panel:hover,
    .video-js .vjs-volume-panel.vjs-hover {
      width: 70px;
    }

    .video-js .vjs-volume-level {
      background: #fff;
    }

    .video-js .vjs-volume-bar {
      margin: 0 6px;
      height: 3px;
    }

    .video-js .vjs-mute-control {
      width: 28px;
    }

    /* Hide Picture-in-Picture on desktop (keep for simplicity) */
    .video-js .vjs-picture-in-picture-control {
      display: none !important;
    }

    /* Quality Selector - Compact */
    .video-js .vjs-quality-selector .vjs-menu-content {
      max-height: 180px;
      font-size: 12px;
    }

    .video-js .vjs-quality-selector .vjs-menu-item {
      padding: 5px 10px;
      text-transform: capitalize;
    }

    .video-js .vjs-quality-selector .vjs-menu-item.vjs-selected {
      background-color: rgba(255, 0, 0, 0.8);
    }

    /* Menu styling */
    .video-js .vjs-menu-button-popup .vjs-menu {
      bottom: 36px;
    }

    .video-js .vjs-menu-button-popup .vjs-menu .vjs-menu-content {
      background: rgba(0, 0, 0, 0.95);
      border-radius: 4px;
      padding: 4px 0;
    }

    .video-js .vjs-menu li {
      padding: 5px 10px;
      font-size: 12px;
    }

    .video-js .vjs-menu li:hover {
      background: rgba(255, 255, 255, 0.15);
    }

    /* Hide playback rate button by default (cleaner UI) */
    .video-js .vjs-playback-rate {
      display: none !important;
    }

    /* Show subs/caps button only if subtitles exist */
    .video-js .vjs-subs-caps-button {
      width: 28px;
    }

    /* Captions/Subtitles - Transparent with text stroke */
    .video-js .vjs-text-track-display {
      font-size: 1em;
      pointer-events: none;
    }

    /* Style the actual text track cues - no background, text stroke only */
    .video-js .vjs-text-track-display > div {
      font-size: 22px !important;
      line-height: 1.5 !important;
      font-weight: 600 !important;
    }

    .video-js .vjs-text-track-display > div > div {
      background: transparent !important;
      padding: 0 !important;
      text-shadow:
        -1px -1px 0 #000,
        1px -1px 0 #000,
        -1px 1px 0 #000,
        1px 1px 0 #000,
        -2px 0 0 #000,
        2px 0 0 #000,
        0 -2px 0 #000,
        0 2px 0 #000 !important;
    }

    .video-js .vjs-text-track-display > div > div > div {
      background: transparent !important;
    }

    /* Override native text track styling - transparent with stroke */
    ::cue {
      background-color: transparent !important;
      color: #fff !important;
      font-size: 22px !important;
      font-weight: 600 !important;
      line-height: 1.5 !important;
      text-shadow:
        -1px -1px 0 #000,
        1px -1px 0 #000,
        -1px 1px 0 #000,
        1px 1px 0 #000,
        -2px 0 0 #000,
        2px 0 0 #000,
        0 -2px 0 #000,
        0 2px 0 #000;
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
    }

    /* Webkit specific cue styling */
    video::-webkit-media-text-track-display {
      overflow: visible !important;
    }

    video::-webkit-media-text-track-container {
      overflow: visible !important;
    }

    video::-webkit-media-text-track-display-backdrop {
      background-color: transparent !important;
    }

    /* Fullscreen button */
    .video-js .vjs-fullscreen-control {
      width: 32px;
    }

    .video-js .vjs-fullscreen-control .vjs-icon-placeholder:before {
      font-size: 17px;
    }

    /* Loading spinner */
    .video-js .vjs-loading-spinner {
      border: 3px solid rgba(255, 255, 255, 0.3);
      border-top-color: #ff0000;
      width: 50px;
      height: 50px;
      margin: -25px 0 0 -25px;
    }

    /* Tooltip */
    .video-js .vjs-time-tooltip,
    .video-js .vjs-mouse-display .vjs-time-tooltip {
      font-size: 10px;
      padding: 3px 5px;
      border-radius: 3px;
    }

    /* Seek bar tooltip */
    .video-js .vjs-mouse-display {
      z-index: 1;
    }

    /* Download button compact */
    .video-js .vjs-download-button {
      width: 28px;
    }

    /* DPlayer Styles */
    .dplayer {
      width: 100%;
      height: 100%;
    }

    .dplayer .dplayer-controller .dplayer-bar-wrap .dplayer-bar .dplayer-played {
      background: #ff0000;
    }

    .dplayer .dplayer-controller .dplayer-icons .dplayer-volume .dplayer-volume-bar-wrap .dplayer-volume-bar .dplayer-volume-now {
      background: #ff0000;
    }

    .dplayer .dplayer-controller .dplayer-icons .dplayer-icon:hover {
      background: rgba(255, 255, 255, 0.1);
    }

    .dplayer .dplayer-menu {
      display: none !important;
    }

    /* Error Display */
    .video-error {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
      color: #fff;
      text-align: center;
      padding: 30px;
    }

    .video-error .error-icon {
      width: 80px;
      height: 80px;
      margin-bottom: 20px;
      opacity: 0.5;
    }

    .video-error h2 {
      font-size: 22px;
      font-weight: 600;
      margin-bottom: 10px;
    }

    .video-error p {
      font-size: 14px;
      color: rgba(255, 255, 255, 0.7);
      max-width: 400px;
    }

    .video-error .status-badge {
      display: inline-block;
      background: rgba(255, 255, 255, 0.1);
      padding: 8px 16px;
      border-radius: 20px;
      margin-top: 15px;
      font-size: 13px;
    }

    .video-error .progress-bar {
      width: 200px;
      height: 4px;
      background: rgba(255, 255, 255, 0.1);
      border-radius: 2px;
      margin-top: 20px;
      overflow: hidden;
    }

    .video-error .progress-bar-fill {
      height: 100%;
      background: #4ade80;
      border-radius: 2px;
    }

    /* Ads Overlay */
    .ads-overlay {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      z-index: 100;
      display: flex;
      align-items: center;
      justify-content: center;
      background: rgba(0, 0, 0, 0.95);
    }

    .ads-overlay.hidden {
      display: none;
    }

    .ads-content {
      position: relative;
      max-width: 90%;
      max-height: 90%;
    }

    .skip-ad-btn {
      position: absolute;
      bottom: 80px;
      right: 20px;
      background: rgba(0, 0, 0, 0.8);
      color: #fff;
      border: 1px solid rgba(255, 255, 255, 0.3);
      padding: 12px 24px;
      border-radius: 4px;
      cursor: pointer;
      font-size: 14px;
      font-weight: 500;
      transition: all 0.2s ease;
      z-index: 101;
    }

    .skip-ad-btn:hover:not(:disabled) {
      background: rgba(255, 255, 255, 0.2);
    }

    .skip-ad-btn:disabled {
      opacity: 0.6;
      cursor: not-allowed;
    }

    .ad-label {
      position: absolute;
      top: 20px;
      left: 20px;
      background: rgba(255, 193, 7, 0.9);
      color: #000;
      padding: 4px 12px;
      border-radius: 3px;
      font-size: 12px;
      font-weight: 600;
      text-transform: uppercase;
      z-index: 101;
    }

    /* Video Title Overlay */
    .video-title-overlay {
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      padding: 15px 20px;
      background: linear-gradient(to bottom, rgba(0,0,0,0.7) 0%, transparent 100%);
      color: #fff;
      font-size: 15px;
      font-weight: 500;
      z-index: 10;
      pointer-events: none;
      opacity: 0;
      transition: opacity 0.3s ease;
    }

    .video-wrapper:hover .video-title-overlay,
    .plyr--paused .video-title-overlay {
      opacity: 1;
    }

    .plyr--playing .video-title-overlay {
      opacity: 0;
    }

    /* Quality badge */
    .quality-badge {
      position: absolute;
      top: 20px;
      right: 20px;
      background: rgba(255, 0, 0, 0.8);
      color: #fff;
      padding: 4px 10px;
      border-radius: 3px;
      font-size: 11px;
      font-weight: 600;
      z-index: 10;
      pointer-events: none;
    }

    /* Overlay Ad Styles */
    .overlay-ad-container {
      position: absolute;
      bottom: 60px;
      left: 50%;
      transform: translateX(-50%);
      z-index: 50;
      max-width: 90%;
      background: rgba(0, 0, 0, 0.85);
      border-radius: 6px;
      padding: 10px;
      display: none;
    }

    .overlay-ad-container.show {
      display: block;
    }

    .overlay-ad-close {
      position: absolute;
      top: -10px;
      right: -10px;
      width: 24px;
      height: 24px;
      background: rgba(255, 255, 255, 0.9);
      border: none;
      border-radius: 50%;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 14px;
      font-weight: bold;
      color: #333;
      z-index: 51;
      transition: all 0.2s ease;
    }

    .overlay-ad-close:hover {
      background: #fff;
      transform: scale(1.1);
    }

    .overlay-ad-content {
      max-width: 728px;
      max-height: 90px;
      overflow: hidden;
    }

    .overlay-ad-label {
      position: absolute;
      top: -8px;
      left: 10px;
      background: rgba(255, 193, 7, 0.9);
      color: #000;
      padding: 2px 8px;
      border-radius: 3px;
      font-size: 10px;
      font-weight: 600;
      text-transform: uppercase;
    }

    /* Native Ad Styles */
    .native-ad-container {
      position: absolute;
      bottom: 0;
      left: 0;
      right: 0;
      z-index: 40;
      background: rgba(0, 0, 0, 0.9);
      padding: 10px;
      text-align: center;
      border-top: 1px solid rgba(255, 255, 255, 0.1);
    }

    .native-ad-container::before {
      content: 'Advertisement';
      display: block;
      font-size: 10px;
      color: rgba(255, 255, 255, 0.5);
      text-transform: uppercase;
      margin-bottom: 5px;
    }

    /* Download Button Styles */
    .download-btn-wrapper {
      position: relative;
      display: inline-flex;
      align-items: center;
      justify-content: center;
    }

    .download-btn {
      background: none;
      border: none;
      cursor: pointer;
      padding: 7px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #fff;
      transition: all 0.2s ease;
    }

    .download-btn:hover {
      background: rgba(255, 255, 255, 0.1);
      border-radius: 3px;
    }

    .download-btn svg {
      width: 22px;
      height: 22px;
      fill: currentColor;
    }

    .download-menu {
      position: absolute;
      bottom: 45px;
      right: 0;
      background: rgba(0, 0, 0, 0.95);
      border-radius: 4px;
      min-width: 160px;
      padding: 5px 0;
      z-index: 100;
      display: none;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.5);
    }

    .download-menu.show {
      display: block;
    }

    .download-menu-header {
      padding: 8px 15px;
      color: rgba(255, 255, 255, 0.6);
      font-size: 11px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    .download-item {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 10px 15px;
      color: #fff;
      cursor: pointer;
      font-size: 14px;
      text-decoration: none;
      transition: background 0.2s;
    }

    .download-item:hover {
      background: rgba(255, 255, 255, 0.15);
    }

    .download-item .quality-label {
      font-weight: 500;
    }

    .download-item .quality-badge {
      position: static;
      background: rgba(255, 0, 0, 0.8);
      padding: 2px 6px;
      border-radius: 3px;
      font-size: 10px;
      font-weight: 600;
    }

    /* VideoJS Download Button */
    .video-js .vjs-download-button {
      cursor: pointer;
      flex: none;
    }

    .video-js .vjs-download-button .vjs-icon-placeholder:before {
      content: '';
      display: block;
      width: 100%;
      height: 100%;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='white'%3E%3Cpath d='M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z'/%3E%3C/svg%3E");
      background-repeat: no-repeat;
      background-position: center;
      background-size: 22px;
    }

    /* Mobile optimizations */
    @media (max-width: 768px) {
      body {
        -webkit-text-size-adjust: 100%;
        touch-action: manipulation;
      }

      /* VideoJS mobile */
      .video-js .vjs-big-play-button {
        width: 56px;
        height: 56px;
      }

      .video-js .vjs-big-play-button .vjs-icon-placeholder:before {
        font-size: 28px;
        line-height: 52px;
      }

      .video-js .vjs-control-bar {
        height: 34px;
      }

      .video-js .vjs-control {
        height: 34px;
      }

      .video-js .vjs-button > .vjs-icon-placeholder:before {
        line-height: 34px;
      }

      .video-js .vjs-time-control {
        line-height: 34px;
        font-size: 10px;
      }

      /* Hide time divider on mobile for space */
      .video-js .vjs-time-divider {
        display: none !important;
      }

      /* Just show current time on mobile */
      .video-js .vjs-duration {
        display: none !important;
      }

      /* Caption styling for mobile */
      ::cue {
        font-size: 16px !important;
      }

      .video-js .vjs-text-track-display > div {
        font-size: 16px !important;
      }

      .video-js .vjs-text-track-display > div > div {
        text-shadow:
          -1px -1px 0 #000,
          1px -1px 0 #000,
          -1px 1px 0 #000,
          1px 1px 0 #000 !important;
      }

      /* DPlayer mobile */
      .dplayer .dplayer-controller {
        padding: 0 8px;
      }

      .skip-ad-btn {
        padding: 10px 20px;
        font-size: 14px;
        bottom: 60px;
        right: 10px;
        min-width: 90px;
        min-height: 40px;
      }

      .video-title-overlay {
        font-size: 13px;
        padding: 10px 14px;
      }

      .ad-label {
        font-size: 10px;
        padding: 4px 10px;
      }
    }

    /* Extra small devices (phones in portrait) */
    @media (max-width: 480px) {
      .video-js .vjs-big-play-button {
        width: 48px;
        height: 48px;
      }

      .video-js .vjs-big-play-button .vjs-icon-placeholder:before {
        font-size: 24px;
        line-height: 44px;
      }

      .video-js .vjs-control-bar {
        height: 32px;
      }

      .video-js .vjs-control {
        width: 24px;
        height: 32px;
      }

      .video-js .vjs-button > .vjs-icon-placeholder:before {
        font-size: 14px;
        line-height: 32px;
      }

      /* Caption styling for small screens */
      ::cue {
        font-size: 14px !important;
      }

      .video-js .vjs-text-track-display > div {
        font-size: 14px !important;
      }

      .video-js .vjs-text-track-display > div > div {
        text-shadow:
          -1px -1px 0 #000,
          1px -1px 0 #000,
          -1px 1px 0 #000,
          1px 1px 0 #000 !important;
      }

      .video-title-overlay {
        font-size: 12px;
        padding: 8px 10px;
      }

      .skip-ad-btn {
        padding: 8px 14px;
        font-size: 12px;
        bottom: 50px;
      }
    }

    /* Landscape mode on mobile */
    @media (max-width: 896px) and (orientation: landscape) {
      .video-title-overlay {
        display: none;
      }

      .video-js .vjs-control-bar {
        height: 30px;
      }

      .skip-ad-btn {
        bottom: 40px;
        right: 8px;
        padding: 6px 12px;
        font-size: 11px;
        min-height: 32px;
      }
    }
  </style>
</head>
<body>
  <div class="video-wrapper">
    @if($video['status'] !== 'completed')
      <!-- Processing State -->
      <div class="video-error">
        <svg class="error-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="10"></circle>
          <polyline points="12,6 12,12 16,14"></polyline>
        </svg>
        <h2>Video Processing</h2>
        <p>This video is currently being processed. Please check back in a few moments.</p>
        <div class="status-badge">
          Status: {{ $video['status'] }}
        </div>
        @if($video['processingProgress'] > 0)
          <div class="progress-bar">
            <div class="progress-bar-fill" style="width: {{ $video['processingProgress'] }}%"></div>
          </div>
          <p style="margin-top: 10px; font-size: 13px;">{{ $video['processingProgress'] }}% complete</p>
        @endif
      </div>
    @else
      <!-- Pre-roll Ad -->
      @if(isset($adsSettings) && isset($adsSettings['enabled']) && $adsSettings['enabled'] && isset($adsSettings['preRollAd']) && isset($adsSettings['preRollAd']['enabled']) && $adsSettings['preRollAd']['enabled'])
        <div id="preroll-ad" class="ads-overlay">
      @endif

      <!-- Videojs Player -->
      @if($selectedPlayer === 'videojs')
        <video id="video-player" class="video-js vjs-16-9 vjs-big-play-centered" controls preload="auto">
          <source src="{{ $video['hlsPlaylistUrl'] }}" type="application/x-mpegURL" />
        </video>
      @elseif($selectedPlayer === 'dplayer')
        <div id="dplayer" class="dplayer"></div>
      @endif

      @if(isset($adsSettings) && isset($adsSettings['enabled']) && $adsSettings['enabled'] && isset($adsSettings['preRollAd']) && isset($adsSettings['preRollAd']['enabled']) && $adsSettings['preRollAd']['enabled'])
        </div>
      @endif
    @endif
  </div>

  <script>
    // Initialize player
    document.addEventListener('DOMContentLoaded', function() {
      @if($video['status'] === 'completed')
        // Determine which player to use based on user settings
        const playerSettings = @json($playerSettings);
        const selectedPlayer = (playerSettings && playerSettings.player) || 'videojs';

        if (selectedPlayer === 'videojs') {
          // VideoJS player initialization with custom settings
          const options = {
            fluid: playerSettings.fluid !== false,
            responsive: playerSettings.responsive !== false,
            aspectRatio: playerSettings.aspectRatio || '16:9',
            autoplay: playerSettings.autoplay || 'muted', // Default to muted for better UX
            controls: playerSettings.controls !== undefined ? playerSettings.controls : true,
            preload: playerSettings.preload || 'auto',
            playbackRates: playerSettings.playbackRates || [0.5, 0.75, 1, 1.25, 1.5, 2],
            responsive: true,
            controlBar: {
              captionsButton: true,
              chaptersButton: false,
              descriptionsButton: false,
              fullscreenToggle: true,
              pictureInPictureToggle: playerSettings.pictureInPicture !== false,
              playbackRateMenuButton: playerSettings.showPlaybackRate !== false,
              qualitySelector: playerSettings.showQualitySelector !== false,
              volumePanel: playerSettings.showVolumePanel !== false,
            },
            breakpoints: {
              tiny: playerSettings.tinyScreen ? parseInt(playerSettings.tinyScreen) : 210,
              xsmall: playerSettings.xsmallScreen ? parseInt(playerSettings.xsmallScreen) : 320,
              small: playerSettings.smallScreen ? parseInt(playerSettings.smallScreen) : 425,
              medium: playerSettings.mediumScreen ? parseInt(playerSettings.mediumScreen) : 768,
              large: playerSettings.largeScreen ? parseInt(playerSettings.largeScreen) : 1440,
              xlarge: playerSettings.xlargeScreen ? parseInt(playerSettings.xlargeScreen) : 2560,
            }
          };

          const player = videojs('video-player', options);

          // HLS support
          if (Hls.isSupported()) {
            const hls = new Hls({
              enableWorker: true,
              lowLatencyMode: true,
              backBufferLength: 90,
            });
            hls.loadSource('{{ $video["hlsPlaylistUrl"] }}');
            hls.attachMedia(document.getElementById('video-player'));

            hls.on(Hls.Events.MANIFEST_PARSED, function() {
              @if(isset($playerSettings['startQuality']) && $playerSettings['startQuality'] !== 'auto')
                const availableQualities = hls.levels;
                const desiredQuality = '{{ $playerSettings['startQuality'] ?? 'auto' }}';
                // Find and set the quality level based on settings
                if(desiredQuality && desiredQuality !== 'auto') {
                  const qualityIndex = availableQualities.findIndex(level =>
                    level.height === parseInt(desiredQuality) ||
                    level.width === parseInt(desiredQuality)
                  );
                  if(qualityIndex !== -1) {
                    hls.nextLevel = qualityIndex;
                  }
                }
              @endif
            });
          } else if (document.getElementById('video-player').canPlayType('application/vnd.apple.mpegurl')) {
            document.getElementById('video-player').src = '{{ $video["hlsPlaylistUrl"] }}';
          }

          // Quality selector - only add if not already present and setting allows it
          if (player.qualityLevels && playerSettings.showQualitySelector !== false) {
            try {
              player.hlsQualitySelector({
                displayCurrentQuality: true,
              });
            } catch(e) {
              console.warn('HLS quality selector not available:', e);
            }
          }

          // Watermark - use the video's watermark setting
          @if(isset($video['watermark']) && $video['watermark']['enabled'])
            const watermarkOptions = {
              file: '{{ $video['watermark']['imagePath'] ?? '' }}',
              xpos: {{ $video['watermark']['position']['x'] ?? 10 }},
              ypos: {{ $video['watermark']['position']['y'] ?? 10 }},
              xrepeat: 0,
              opacity: {{ $video['watermark']['opacity'] ?? 0.5 }}
            };
            player.ready(() => {
              if (player.watermark) {
                player.watermark(watermarkOptions);
              }
            });
          @endif

          // Subtitles support - use video subtitles
          @if(isset($video['subtitles']) && $video['subtitles'])
            @foreach($video['subtitles'] as $subtitle)
              player.addRemoteTextTrack({
                kind: 'subtitles',
                label: '{{ $subtitle['label'] ?? $subtitle['language'] }}',
                language: '{{ $subtitle['language'] }}',
                src: '{{ $subtitle['filePath'] }}',
                default: '{{ $subtitle['language'] }}' === 'en'
              });
            @endforeach
          @endif

          // Apply subtitle settings if available
          @if(isset($subtitleSettings) && !empty($subtitleSettings))
            player.ready(() => {
              const textTracks = player.textTracks();
              for (let i = 0; i < textTracks.length; i++) {
                const track = textTracks[i];
                if (track.kind === 'subtitles' || track.kind === 'captions') {
                  // Apply user subtitle preferences
                  if (typeof player.tech().setSubtitlesStyle === 'function') {
                    player.tech().setSubtitlesStyle({
                      'font-size': '{{ $subtitleSettings['fontSize'] ?? "16px" }}',
                      'font-family': '{{ $subtitleSettings['fontFamily'] ?? "Arial, sans-serif" }}',
                      'color': '{{ $subtitleSettings['color'] ?? "#FFF" }}',
                      'text-shadow': '2px 2px 4px #000',
                      'background-color': '{{ $subtitleSettings['backgroundColor'] ?? "transparent" }}',
                      'padding': '2px'
                    });
                  }
                }
              }
            });
          @endif

        } else if (selectedPlayer === 'dplayer') {
          // DPlayer initialization with custom settings
          const dplayerOptions = {
            container: document.getElementById('dplayer'),
            autoplay: {{ (isset($playerSettings['autoplay']) && $playerSettings['autoplay'] === true) ? 'true' : 'false' }},
            screenshot: {{ (isset($playerSettings['screenshot']) && $playerSettings['screenshot']) ? 'true' : 'false' }},
            hotkey: {{ (isset($playerSettings['hotkey']) && $playerSettings['hotkey'] !== false) ? 'true' : 'false' }},
            preload: '{{ $playerSettings['preload'] ?? "auto" }}',
            volume: {{ $playerSettings['volume'] ?? 0.7 }},
            video: {
              url: '{{ $video["hlsPlaylistUrl"] }}',
              type: 'hls'
            },
            @if(isset($video['subtitles']) && $video['subtitles'])
              subtitle: {
                url: '{{ $video["subtitles"][0]['filePath'] ?? '' }}',
                fontSize: '{{ $subtitleSettings['fontSize'] ?? "24px" }}',
                bottom: '{{ $subtitleSettings['position'] ?? "40px" }}',
                color: '{{ $subtitleSettings['color'] ?? "#fff" }}'
              }
            @endif
          };

          const dp = new DPlayer(dplayerOptions);
        }

        // Handle pre-roll ads
        @if(isset($adsSettings) && isset($adsSettings['enabled']) && $adsSettings['enabled'] && isset($adsSettings['preRollAd']) && isset($adsSettings['preRollAd']['enabled']) && $adsSettings['preRollAd']['enabled'])
          // Show pre-roll ad first
          const adElement = document.getElementById('preroll-ad');
          if (adElement) {
            setTimeout(() => {
              adElement.style.display = 'flex';
            }, 100);

            // Skip ad after countdown
            let countdown = {{ $adsSettings['preRollAd']['skipAfter'] ?? 5 }};
            const skipBtn = document.querySelector('.skip-ad-btn');
            if (skipBtn) {
              const updateSkipBtn = () => {
                if (countdown <= 0) {
                  skipBtn.textContent = 'Skip Ad';
                  skipBtn.disabled = false;
                } else {
                  skipBtn.textContent = `Skip Ad (${countdown}s)`;
                  skipBtn.disabled = true;
                  countdown--;
                  setTimeout(updateSkipBtn, 1000);
                }
              };
              updateSkipBtn();

              skipBtn.addEventListener('click', () => {
                adElement.style.display = 'none';
                // Start the actual video player
                if (selectedPlayer === 'videojs') {
                  player.play();
                } else if (selectedPlayer === 'dplayer') {
                  dp.play();
                }
              });
            }
          }
        @else
          // Start player immediately if no ads
          setTimeout(() => {
            if (selectedPlayer === 'videojs') {
              player.play();
            } else if (selectedPlayer === 'dplayer') {
              dp.play();
            }
          }, 100);
        @endif
      @endif
    });
  </script>

  <!-- Player Libraries -->
  @if($selectedPlayer === 'videojs')
    <script src="https://vjs.zencdn.net/8.6.1/video.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/hls.js@1.4.10/dist/hls.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/videojs-hls-quality-selector@2.0.0/dist/videojs-hls-quality-selector.min.js"></script>
    @if(isset($video['watermark']) && $video['watermark']['enabled'])
      <script src="https://cdn.jsdelivr.net/npm/videojs-watermark@2.0.3/src/videojs-watermark.min.js"></script>
    @endif
  @elseif($selectedPlayer === 'dplayer')
    <script src="https://cdn.jsdelivr.net/npm/dplayer@1.27.1/dist/DPlayer.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/hls.js@1.4.10/dist/hls.min.js"></script>
  @endif
</body>
</html>