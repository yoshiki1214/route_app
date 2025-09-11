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

// 座標からPlace IDを取得する関数（リバースジオコーディング）
window.getPlaceIdFromCoordinates = function (lat, lng, callback) {
  if (!window.googleMapsReady) {
    console.error('Google Maps API is not ready');
    return;
  }

  const geocoder = new google.maps.Geocoder();
  const latlng = { lat: parseFloat(lat), lng: parseFloat(lng) };

  geocoder.geocode({ location: latlng }, function (results, status) {
    console.log('Reverse Geocoding API Response:', { results, status });

    if (status === 'OK' && results[0]) {
      const placeId = results[0].place_id;
      console.log('Place ID retrieved:', placeId);
      console.log('Formatted address:', results[0].formatted_address);

      // リバースジオコーディングの結果から直接情報を取得
      const result = results[0];
      const addressComponents = result.address_components;

      // 店舗名を抽出（可能な限り）
      let placeName = '';
      let establishmentName = '';

      for (let component of addressComponents) {
        if (component.types.includes('establishment')) {
          establishmentName = component.long_name;
        } else if (component.types.includes('point_of_interest')) {
          placeName = component.long_name;
        } else if (component.types.includes('store') ||
          component.types.includes('restaurant') ||
          component.types.includes('food')) {
          placeName = component.long_name;
        }
      }

      // 店舗名の優先順位: establishment > point_of_interest > store/restaurant
      const finalPlaceName = establishmentName || placeName;

      // 住所から店舗名を分離（フォーマットされた住所の最初の部分が店舗名の場合）
      let cleanAddress = result.formatted_address;
      let extractedName = '';

      if (finalPlaceName) {
        // 既に店舗名が抽出されている場合はそれを使用
        extractedName = finalPlaceName;
      } else {
        // フォーマットされた住所を解析して店舗名を抽出
        const addressParts = result.formatted_address.split(',');
        if (addressParts.length > 1) {
          const firstPart = addressParts[0].trim();
          const secondPart = addressParts[1].trim();

          // 最初の部分が数字で始まる場合は住所、そうでなければ店舗名の可能性
          if (!/^\d/.test(firstPart) && firstPart.length > 0) {
            extractedName = firstPart;
            // 住所から店舗名部分を除去
            cleanAddress = addressParts.slice(1).join(',').trim();
          }
        }
      }

      console.log('Extracted place name:', extractedName);
      console.log('Clean address:', cleanAddress);

      // フォールバック: 新しいPlaces APIが利用できない場合、Geocodingの結果を使用
      callback({
        place_id: placeId,
        formatted_address: cleanAddress,
        name: extractedName || '',
        lat: lat,
        lng: lng,
        use_geocoding_result: true // フラグを追加
      });
    } else {
      console.error('Reverse geocoding failed:', status, results);
      callback(null);
    }
  });
};

// Place IDから詳細情報を取得する関数（新しいPlaces API使用）
window.getPlaceDetails = function (placeId, callback) {
  if (!window.googleMapsReady) {
    console.error('Google Maps API is not ready');
    return;
  }

  console.log('Getting place details for Place ID:', placeId);

  // 新しいPlaces APIの使用
  if (window.google && window.google.maps && window.google.maps.places) {
    // 新しいAPIでは、Placeオブジェクトをidで初期化
    const place = new google.maps.places.Place({
      id: placeId
    });

    // 必要なフィールドを指定してfetchFieldsを呼び出し
    place.fetchFields({
      fields: ['displayName', 'formattedAddress', 'internationalPhoneNumber', 'location', 'website']
    }).then((result) => {
      console.log('Place Details API Response (New):', result);

      if (result && result.place) {
        const place = result.place;
        console.log('Place details retrieved:', {
          name: place.displayName,
          address: place.formattedAddress,
          phone: place.internationalPhoneNumber,
          location: place.location,
          website: place.website
        });

        callback({
          name: place.displayName || '',
          address: place.formattedAddress || '',
          phone: place.internationalPhoneNumber || '',
          lat: place.location?.lat || null,
          lng: place.location?.lng || null,
          website: place.website || ''
        });
      } else {
        console.error('Place details failed (New API):', result);
        callback(null);
      }
    }).catch((error) => {
      console.error('Place details error (New API):', error);
      callback(null);
    });
  } else {
    console.error('New Places API not available');
    callback(null);
  }
};

// URLから座標を抽出する関数
window.extractCoordinatesFromUrl = function (url, callback) {
  console.log('Extracting coordinates from URL:', url);

  // @緯度,経度,ズーム の形式を検索
  const coordMatch = url.match(/@([0-9.-]+),([0-9.-]+),([0-9.]+)z/);
  if (coordMatch) {
    const coords = {
      lat: parseFloat(coordMatch[1]),
      lng: parseFloat(coordMatch[2]),
      zoom: parseFloat(coordMatch[3])
    };
    console.log('Coordinates extracted:', coords);
    callback(coords);
  } else {
    console.log('No coordinates found in URL');
    callback(null);
  }
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

  // Google Maps APIの読み込みを開始
  if (window.loadGoogleMaps) {
    window.loadGoogleMaps();
  }
});
