document.addEventListener("DOMContentLoaded", () => {
  initRouteMap();
  initElevationProfile();
  initRoutesOverviewMap();
  initCopyRouteLinks();
});

function initRouteMap() {
  const mapEl = document.getElementById("route-map");
  if (!mapEl || typeof L === "undefined") return;

  let coordinates = [];
  try {
    coordinates = JSON.parse(mapEl.dataset.route || "[]");
  } catch (error) {
    coordinates = [];
  }

  const latLngs = coordinates
    .map((point) => [parseFloat(point.lat), parseFloat(point.lng)])
    .filter((point) => Number.isFinite(point[0]) && Number.isFinite(point[1]));
  const isSurfMap = (mapEl.dataset.activity || "").toLowerCase() === "surf";

  const map = L.map(mapEl).setView([43.3614, -5.8494], 8);
  L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
    maxZoom: 19,
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
  }).addTo(map);
  bindLeafletResize(map);
  map.on("popupopen", () => keepLeafletPopupInsideMap(map));

  if (latLngs.length === 0) return;

  const polyline = L.polyline(latLngs, {
    color: isSurfMap ? "#117f9b" : "#1e6f50",
    weight: isSurfMap ? 4 : 5,
    opacity: 0.95,
  }).addTo(map);

  const popupOptions = {
    autoPan: true,
    autoPanPaddingTopLeft: [24, 120],
    autoPanPaddingBottomRight: [24, 24],
    keepInView: true,
  };

  L.marker(latLngs[0]).addTo(map).bindPopup(isSurfMap ? "Inicio del spot" : "Inicio", popupOptions);
  L.marker(latLngs[latLngs.length - 1]).addTo(map).bindPopup(isSurfMap ? "Fin del spot" : "Fin", popupOptions);
  map.fitBounds(polyline.getBounds(), {
    paddingTopLeft: [28, 120],
    paddingBottomRight: [28, 28],
  });
}

function initElevationProfile() {
  const profileEl = document.getElementById("route-elevation-profile");
  if (!profileEl) return;

  let profile = [];
  try {
    profile = JSON.parse(profileEl.dataset.profile || "[]");
  } catch (error) {
    profile = [];
  }

  const points = profile
    .map((point) => ({
      distance: parseFloat(point.distance_km),
      elevation: parseFloat(point.elevation_m),
    }))
    .filter((point) => Number.isFinite(point.distance) && Number.isFinite(point.elevation));

  if (points.length < 2) {
    profileEl.innerHTML = '<p class="muted">Todavia no hay suficientes datos para dibujar el perfil.</p>';
    return;
  }

  const width = 820;
  const height = 260;
  const padding = { top: 26, right: 22, bottom: 46, left: 52 };
  const chartWidth = width - padding.left - padding.right;
  const chartHeight = height - padding.top - padding.bottom;

  const distances = points.map((point) => point.distance);
  const elevations = points.map((point) => point.elevation);
  const minDistance = Math.min(...distances);
  const maxDistance = Math.max(...distances);
  const minElevation = Math.min(...elevations);
  const maxElevation = Math.max(...elevations);
  const safeDistanceRange = Math.max(0.001, maxDistance - minDistance);
  const safeElevationRange = Math.max(1, maxElevation - minElevation);

  const scaleX = (value) => padding.left + ((value - minDistance) / safeDistanceRange) * chartWidth;
  const scaleY = (value) => padding.top + chartHeight - ((value - minElevation) / safeElevationRange) * chartHeight;

  const linePath = points
    .map((point, index) => `${index === 0 ? "M" : "L"} ${scaleX(point.distance).toFixed(2)} ${scaleY(point.elevation).toFixed(2)}`)
    .join(" ");
  const areaPath = `${linePath} L ${scaleX(maxDistance).toFixed(2)} ${padding.top + chartHeight} L ${scaleX(minDistance).toFixed(2)} ${padding.top + chartHeight} Z`;

  const gridLines = [0, 0.25, 0.5, 0.75, 1].map((ratio) => {
    const y = padding.top + chartHeight * ratio;
    return `<line x1="${padding.left}" y1="${y.toFixed(2)}" x2="${width - padding.right}" y2="${y.toFixed(2)}"></line>`;
  }).join("");

  const isCompactProfile = (profileEl.clientWidth || width) < 560;
  const distanceTickRatios = isCompactProfile ? [0, 0.5, 1] : [0, 0.25, 0.5, 0.75, 1];
  const distanceTicks = distanceTickRatios.map((ratio, index) => {
    const x = padding.left + chartWidth * ratio;
    const distance = minDistance + safeDistanceRange * ratio;
    const anchor = index === 0 ? "start" : (index === distanceTickRatios.length - 1 ? "end" : "middle");
    return `
      <line class="elevation-axis-tick" x1="${x.toFixed(2)}" y1="${padding.top + chartHeight}" x2="${x.toFixed(2)}" y2="${padding.top + chartHeight + 8}"></line>
      <text x="${x.toFixed(2)}" y="${height - 12}" text-anchor="${anchor}">${distance.toFixed(1)} km</text>
    `;
  }).join("");

  const elevationTicks = [maxElevation, (maxElevation + minElevation) / 2, minElevation].map((value) => (
    `<text x="${padding.left - 10}" y="${(scaleY(value) + 4).toFixed(2)}" text-anchor="end">${Math.round(value)} m</text>`
  )).join("");

  profileEl.innerHTML = `
    <div class="elevation-tooltip" hidden></div>
    <svg viewBox="0 0 ${width} ${height}" role="img" aria-label="Perfil de altitud de la ruta">
      <defs>
        <linearGradient id="elevationAreaFill" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stop-color="#2b8a61" stop-opacity="0.38"></stop>
          <stop offset="100%" stop-color="#2b8a61" stop-opacity="0.04"></stop>
        </linearGradient>
      </defs>
      <g class="elevation-grid">${gridLines}</g>
      <path class="elevation-area" d="${areaPath}"></path>
      <path class="elevation-line" d="${linePath}"></path>
      <line class="elevation-cursor" x1="${padding.left}" y1="${padding.top}" x2="${padding.left}" y2="${padding.top + chartHeight}" hidden></line>
      <circle class="elevation-marker" cx="${padding.left}" cy="${scaleY(points[0].elevation).toFixed(2)}" r="4.5" hidden></circle>
      <g class="elevation-axis-labels">${distanceTicks}${elevationTicks}</g>
    </svg>
  `;

  const svg = profileEl.querySelector("svg");
  const tooltip = profileEl.querySelector(".elevation-tooltip");
  const cursor = profileEl.querySelector(".elevation-cursor");
  const marker = profileEl.querySelector(".elevation-marker");
  if (!svg || !tooltip || !cursor || !marker) return;

  const updatePointer = (clientX) => {
    const bounds = svg.getBoundingClientRect();
    if (!bounds.width) return;

    const relativeX = ((clientX - bounds.left) / bounds.width) * width;
    const clampedX = Math.max(padding.left, Math.min(width - padding.right, relativeX));
    const targetDistance = minDistance + ((clampedX - padding.left) / chartWidth) * safeDistanceRange;

    let nearest = points[0];
    let nearestIndex = 0;
    for (let index = 1; index < points.length; index++) {
      if (Math.abs(points[index].distance - targetDistance) < Math.abs(nearest.distance - targetDistance)) {
        nearest = points[index];
        nearestIndex = index;
      }
    }

    const pointX = scaleX(nearest.distance);
    const pointY = scaleY(nearest.elevation);
    cursor.setAttribute("x1", pointX.toFixed(2));
    cursor.setAttribute("x2", pointX.toFixed(2));
    cursor.hidden = false;
    marker.setAttribute("cx", pointX.toFixed(2));
    marker.setAttribute("cy", pointY.toFixed(2));
    marker.hidden = false;

    tooltip.hidden = false;
    tooltip.innerHTML = `<strong>${nearest.distance.toFixed(1)} km</strong><span>${Math.round(nearest.elevation)} m</span>`;
    tooltip.classList.remove("is-below");

    const tooltipWidth = tooltip.offsetWidth || 96;
    const tooltipHeight = tooltip.offsetHeight || 52;
    const horizontalMargin = 10;
    const verticalMargin = 8;

    let leftPx = pointX - (tooltipWidth / 2);
    leftPx = Math.max(horizontalMargin, Math.min(leftPx, width - tooltipWidth - horizontalMargin));

    let topPx = pointY - tooltipHeight - 18;
    if (topPx < verticalMargin) {
      topPx = Math.min(height - tooltipHeight - verticalMargin, pointY + 16);
      tooltip.classList.add("is-below");
    }

    tooltip.style.left = `${(leftPx / width) * 100}%`;
    tooltip.style.top = `${(topPx / height) * 100}%`;
    tooltip.dataset.index = String(nearestIndex);
  };

  svg.addEventListener("pointermove", (event) => updatePointer(event.clientX));
  svg.addEventListener("pointerenter", (event) => updatePointer(event.clientX));
  svg.addEventListener("pointerleave", () => {
    tooltip.hidden = true;
    cursor.hidden = true;
    marker.hidden = true;
  });
}

function initRoutesOverviewMap() {
  const mapEl = document.getElementById("routes-overview-map");
  if (!mapEl || typeof L === "undefined") return;

  let routes = [];
  try {
    routes = JSON.parse(mapEl.dataset.routes || "[]");
  } catch (error) {
    routes = [];
  }
  if (!Array.isArray(routes) || !routes.length) return;

  const searchInput = document.getElementById("map-search");
  const activityFilter = document.getElementById("map-activity-filter");
  const difficultyFilter = document.getElementById("map-difficulty-filter");
  const countEl = document.getElementById("map-route-count");
  const mapEmptyState = document.getElementById("map-empty-state");
  const listEmptyState = document.getElementById("map-list-empty-state");
  const routeCards = Array.from(document.querySelectorAll(".map-route-card"));
  const focusButtons = Array.from(document.querySelectorAll(".js-map-focus-route"));
  const clearFilterButtons = Array.from(document.querySelectorAll(".js-map-clear-filters"));

  const map = L.map(mapEl).setView([43.3614, -5.8494], 8);
  L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
    maxZoom: 19,
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
  }).addTo(map);
  bindLeafletResize(map);
  map.on("popupopen", () => keepLeafletPopupInsideMap(map));

  const defaultPolylineStyle = {
    color: "#2b8a61",
    weight: 3,
    opacity: 0.3,
  };
  const highlightedPolylineStyle = {
    color: "#e1862b",
    weight: 6,
    opacity: 0.95,
  };
  const surfPolylineStyle = {
    color: "#117f9b",
    weight: 4,
    opacity: 0.46,
  };
  const highlightedSurfPolylineStyle = {
    color: "#0b6f89",
    weight: 6,
    opacity: 0.96,
  };
  const defaultMarkerStyle = {
    radius: 6,
    color: "#ffffff",
    weight: 2,
    fillColor: "#1e6f50",
    fillOpacity: 0.92,
  };
  const highlightedMarkerStyle = {
    radius: 8,
    color: "#ffffff",
    weight: 2,
    fillColor: "#e1862b",
    fillOpacity: 0.98,
  };
  const surfMarkerIcon = L.divIcon({
    className: "surf-map-marker",
    html: "<span></span>",
    iconSize: [30, 30],
    iconAnchor: [15, 15],
    popupAnchor: [0, -15],
  });
  const highlightedSurfMarkerIcon = L.divIcon({
    className: "surf-map-marker surf-map-marker-active",
    html: "<span></span>",
    iconSize: [34, 34],
    iconAnchor: [17, 17],
    popupAnchor: [0, -17],
  });

  const isSurfRoute = (route) => String(route.activity_type || "").toLowerCase() === "surf" || route.is_surf === true;
  const routeDefaultPolylineStyle = (state) => state.isSurf ? surfPolylineStyle : defaultPolylineStyle;
  const routeHighlightedPolylineStyle = (state) => state.isSurf ? highlightedSurfPolylineStyle : highlightedPolylineStyle;

  const states = routes
    .map((route) => {
      const latLngs = Array.isArray(route.points)
        ? route.points
            .map((point) => [parseFloat(point.lat), parseFloat(point.lng)])
            .filter((point) => Number.isFinite(point[0]) && Number.isFinite(point[1]))
        : [];
      if (latLngs.length < 2) return null;

      const isSurf = isSurfRoute(route);
      const polyline = L.polyline(latLngs, isSurf ? surfPolylineStyle : defaultPolylineStyle).addTo(map);
      const marker = isSurf
        ? L.marker(latLngs[0], { icon: surfMarkerIcon }).addTo(map)
        : L.circleMarker(latLngs[0], defaultMarkerStyle).addTo(map);
      const routeImage = route.cover_image_src
        ? `<img class="map-popup-image" src="${escapeHtml(route.cover_image_src)}" alt="">`
        : "";
      const surf = isSurf && route.surf && typeof route.surf === "object" ? route.surf : null;
      const metricBadges = surf
        ? `
          <span>${escapeHtml(route.difficulty || "")}</span>
          <span>${escapeHtml(surf.level || "Surf")}</span>
          <span>${escapeHtml(surf.tide || "Marea variable")}</span>
        `
        : `
          <span>${escapeHtml(route.difficulty || "")}</span>
          <span>${Number(route.distance_km || 0).toFixed(1)} km</span>
          <span>${Number(route.elevation_m || 0)} m+</span>
        `;
      const surfInfo = surf
        ? `
          <div class="map-popup-surf">
            <span><strong>Viento:</strong> ${escapeHtml(surf.wind || "")}</span>
            <span><strong>Ola:</strong> ${escapeHtml(surf.wave || "")}</span>
          </div>
          <p class="map-popup-warning">${escapeHtml(surf.safety || "")}</p>
        `
        : "";
      const popupHtml = `
        <div class="map-popup">
          ${routeImage}
          <div class="map-popup-body">
            <strong>${escapeHtml(route.name || "Ruta")}</strong>
            <span class="map-popup-meta">${escapeHtml(route.zone || "")} - ${escapeHtml(route.activity_type || "")}</span>
            <div class="map-popup-badges">
              ${metricBadges}
            </div>
            ${surfInfo}
            <a class="button button-small map-popup-action" href="${escapeHtml(route.url || "#")}">Abrir ficha</a>
          </div>
        </div>
      `;
      marker.bindPopup(popupHtml, {
        autoPan: true,
        autoPanPaddingTopLeft: [28, 150],
        autoPanPaddingBottomRight: [28, 300],
        keepInView: true,
        maxWidth: 280,
        minWidth: 220,
      });
      polyline.on("click", () => selectRoute(route.id));
      marker.on("click", () => selectRoute(route.id));

      return {
        ...route,
        latLngs,
        polyline,
        marker,
        searchText: `${route.name || ""} ${route.zone || ""} ${route.activity_type || ""}`.toLowerCase(),
        isSurf,
        visible: true,
      };
    })
    .filter(Boolean);

  if (!states.length) return;

  let selectedRouteId = null;

  function fitVisibleRoutes(visibleStates) {
    if (!visibleStates.length) return;
    const group = L.featureGroup(visibleStates.map((state) => state.polyline));
    const bounds = group.getBounds();
    if (bounds.isValid()) {
      map.fitBounds(bounds, { padding: [28, 28] });
    }
  }

  function setCardState(routeId) {
    routeCards.forEach((card) => {
      const active = String(card.dataset.routeId || "") === String(routeId || "");
      card.classList.toggle("is-active", active);
    });
  }

  function selectRoute(routeId, shouldFit = true) {
    const state = states.find((item) => String(item.id) === String(routeId));
    if (!state || !state.visible) return;

    selectedRouteId = state.id;
    states.forEach((item) => {
      item.polyline.setStyle(item.id === state.id ? routeHighlightedPolylineStyle(item) : routeDefaultPolylineStyle(item));
      if (item.isSurf) {
        item.marker.setIcon(item.id === state.id ? highlightedSurfMarkerIcon : surfMarkerIcon);
      } else {
        item.marker.setStyle(item.id === state.id ? highlightedMarkerStyle : defaultMarkerStyle);
      }
    });
    setCardState(state.id);

    if (shouldFit) {
      const bounds = state.polyline.getBounds();
      if (bounds.isValid()) {
        map.fitBounds(bounds, {
          animate: false,
          paddingTopLeft: [42, 170],
          paddingBottomRight: [42, 42],
        });
      }
    }
    state.marker.openPopup();
    keepLeafletPopupInsideMap(map);
  }

  function updateVisibility() {
    const searchValue = (searchInput?.value || "").trim().toLowerCase();
    const activityValue = activityFilter?.value || "";
    const difficultyValue = difficultyFilter?.value || "";

    const visibleStates = [];
    states.forEach((state) => {
      const matchesSearch = searchValue === "" || state.searchText.includes(searchValue);
      const matchesActivity = activityValue === "" || state.activity_type === activityValue;
      const matchesDifficulty = difficultyValue === "" || state.difficulty === difficultyValue;
      const matches = matchesSearch && matchesActivity && matchesDifficulty;
      state.visible = matches;

      if (matches) {
        if (!map.hasLayer(state.polyline)) state.polyline.addTo(map);
        if (!map.hasLayer(state.marker)) state.marker.addTo(map);
        visibleStates.push(state);
      } else {
        if (map.hasLayer(state.polyline)) map.removeLayer(state.polyline);
        if (map.hasLayer(state.marker)) map.removeLayer(state.marker);
        if (selectedRouteId === state.id) {
          selectedRouteId = null;
        }
      }
    });

    routeCards.forEach((card) => {
      const routeId = String(card.dataset.routeId || "");
      const state = states.find((item) => String(item.id) === routeId);
      card.hidden = !state || !state.visible;
      if (card.hidden) {
        card.classList.remove("is-active");
      }
    });

    if (countEl) {
      countEl.textContent = `${visibleStates.length} rutas visibles`;
    }
    if (mapEmptyState) {
      mapEmptyState.hidden = visibleStates.length !== 0;
    }
    if (listEmptyState) {
      listEmptyState.hidden = visibleStates.length !== 0;
    }

    if (selectedRouteId !== null) {
      selectRoute(selectedRouteId, false);
    } else {
      states.forEach((item) => {
        item.polyline.setStyle(routeDefaultPolylineStyle(item));
        if (item.isSurf) {
          item.marker.setIcon(surfMarkerIcon);
        } else {
          item.marker.setStyle(defaultMarkerStyle);
        }
      });
      fitVisibleRoutes(visibleStates);
    }
  }

  focusButtons.forEach((button) => {
    button.addEventListener("click", () => {
      const routeId = button.dataset.routeId || "";
      if (routeId !== "") {
        selectRoute(routeId);
      }
    });
  });

  searchInput?.addEventListener("input", updateVisibility);
  activityFilter?.addEventListener("change", updateVisibility);
  difficultyFilter?.addEventListener("change", updateVisibility);
  clearFilterButtons.forEach((button) => {
    button.addEventListener("click", () => {
      if (searchInput) searchInput.value = "";
      if (activityFilter) activityFilter.value = "";
      if (difficultyFilter) difficultyFilter.value = "";
      updateVisibility();
    });
  });

  updateVisibility();
}

function bindLeafletResize(map) {
  if (!map || typeof map.invalidateSize !== "function") return;

  const refresh = () => {
    window.setTimeout(() => {
      map.invalidateSize();
    }, 80);
  };

  window.requestAnimationFrame(refresh);
  window.addEventListener("resize", refresh, { passive: true });
  window.addEventListener("orientationchange", refresh);
}

function keepLeafletPopupInsideMap(map) {
  if (!map || typeof map.panBy !== "function") return;

  window.setTimeout(() => {
    const container = map.getContainer?.();
    const popup = container?.querySelector(".leaflet-popup-content-wrapper");
    if (!container || !popup) return;

    const gap = 14;
    const mapRect = container.getBoundingClientRect();
    const popupRect = popup.getBoundingClientRect();
    let panX = 0;
    let panY = 0;

    if (popupRect.left < mapRect.left + gap) {
      panX = popupRect.left - mapRect.left - gap;
    } else if (popupRect.right > mapRect.right - gap) {
      panX = popupRect.right - mapRect.right + gap;
    }

    if (popupRect.top < mapRect.top + gap) {
      panY = popupRect.top - mapRect.top - gap;
    } else if (popupRect.bottom > mapRect.bottom - gap) {
      panY = popupRect.bottom - mapRect.bottom + gap;
    }

    if (panX !== 0 || panY !== 0) {
      map.panBy([panX, panY], { animate: false });
    }
  }, 80);
}

function initCopyRouteLinks() {
  const buttons = document.querySelectorAll(".js-copy-route-link");
  if (!buttons.length) return;

  buttons.forEach((button) => {
    button.addEventListener("click", async () => {
      const url = button.dataset.copyUrl || "";
      if (!url) return;

      const originalText = button.dataset.originalText || button.textContent || "Compartir ruta";
      button.dataset.originalText = originalText;

      let copied = false;
      if (navigator.clipboard && window.isSecureContext) {
        try {
          await navigator.clipboard.writeText(url);
          copied = true;
        } catch (error) {
          copied = false;
        }
      }

      if (!copied) {
        copied = fallbackCopyText(url);
      }

      if (!copied) {
        window.prompt("Copia este enlace:", url);
      }

      button.textContent = copied ? "Enlace copiado" : "Enlace listo";
      button.classList.add("is-copied");
      window.setTimeout(() => {
        button.textContent = originalText;
        button.classList.remove("is-copied");
      }, 2200);
    });
  });
}

function fallbackCopyText(value) {
  const helper = document.createElement("textarea");
  helper.value = value;
  helper.setAttribute("readonly", "readonly");
  helper.style.position = "fixed";
  helper.style.opacity = "0";
  document.body.appendChild(helper);
  helper.focus();
  helper.select();

  let copied = false;
  try {
    copied = document.execCommand("copy");
  } catch (error) {
    copied = false;
  }

  document.body.removeChild(helper);
  return copied;
}

function escapeHtml(value) {
  return String(value)
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#39;");
}
