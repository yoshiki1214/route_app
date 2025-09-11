// Google Maps初期化
window.initGoogleMaps = function () {
  console.log('Google Maps API loaded');
  window.googleMapsReady = true;

  // カスタムイベントを発火
  window.dispatchEvent(new CustomEvent('googleMapsReady'));
};

// グローバル変数
window.googleMapsReady = false;
window.googleMapsInstances = {};

// 住所から緯度経度を取得する関数
window.getLatLngFromAddress = function (address, callback) {
  if (!window.googleMapsReady) {
    console.error('Google Maps API is not ready');
    return;
  }

  const geocoder = new google.maps.Geocoder();
  geocoder.geocode({ address: address }, function (results, status) {
    if (status === 'OK' && results[0]) {
      const location = results[0].geometry.location;
      callback({
        lat: location.lat(),
        lng: location.lng(),
        formatted_address: results[0].formatted_address
      });
    } else {
      console.error('Geocoding failed:', status);
      callback(null);
    }
  });
};

// 地図を初期化する関数
window.initMap = function (elementId, options = {}) {
  if (!window.googleMapsReady) {
    console.error('Google Maps API is not ready');
    return null;
  }

  const defaultOptions = {
    zoom: 15,
    center: { lat: 35.6762, lng: 139.6503 }, // 東京
    mapTypeId: google.maps.MapTypeId.ROADMAP
  };

  const mapOptions = { ...defaultOptions, ...options };
  const map = new google.maps.Map(document.getElementById(elementId), mapOptions);

  window.googleMapsInstances[elementId] = map;
  return map;
};

// マーカーを追加する関数
window.addMarker = function (map, position, title = '', infoWindowContent = '') {
  if (!map) return null;

  const marker = new google.maps.Marker({
    position: position,
    map: map,
    title: title
  });

  if (infoWindowContent) {
    const infoWindow = new google.maps.InfoWindow({
      content: infoWindowContent
    });

    marker.addListener('click', function () {
      infoWindow.open(map, marker);
    });
  }

  return marker;
};

// スクロール位置保持機能
window.scrollPositionManager = {
  position: 0,

  save: function () {
    this.position = window.pageYOffset || document.documentElement.scrollTop;
  },

  restore: function () {
    if (this.position > 0) {
      window.scrollTo(0, this.position);
    }
  },

  init: function () {
    // 入力フィールドのフォーカス時にスクロール位置を保存
    document.addEventListener('focusin', (e) => {
      if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') {
        this.save();
      }
    });

    // Livewireの更新前にスクロール位置を保存
    if (window.Livewire) {
      Livewire.hook('morph.updating', () => {
        this.save();
      });

      // Livewireの更新後にスクロール位置を復元
      Livewire.hook('morph.updated', () => {
        setTimeout(() => this.restore(), 10);
      });
    }
  }
};

// ページ読み込み時にスクロール位置管理を初期化
document.addEventListener('DOMContentLoaded', function () {
  window.scrollPositionManager.init();
});
