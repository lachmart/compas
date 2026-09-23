// Projects [lat, lng] points into an SVG box (north up), keeping the
// real-world aspect ratio so a route isn't stretched.
export function projectPoints(points, width, height, padding) {
    if (points.length === 0) return [];

    const meanLat = points.reduce((sum, [lat]) => sum + lat, 0) / points.length;
    const xScale = Math.cos((meanLat * Math.PI) / 180);
    const xs = points.map(([, lng]) => lng * xScale);
    const ys = points.map(([lat]) => lat);

    const [minX, maxX] = [Math.min(...xs), Math.max(...xs)];
    const [minY, maxY] = [Math.min(...ys), Math.max(...ys)];
    const spanX = maxX - minX;
    const spanY = maxY - minY;
    const innerW = width - 2 * padding;
    const innerH = height - 2 * padding;
    const scale = spanX === 0 && spanY === 0 ? 0 : Math.min(
        spanX === 0 ? Infinity : innerW / spanX,
        spanY === 0 ? Infinity : innerH / spanY,
    );
    const offsetX = padding + (innerW - spanX * scale) / 2;
    const offsetY = padding + (innerH - spanY * scale) / 2;

    return points.map((_, i) => ({
        x: offsetX + (xs[i] - minX) * scale,
        y: offsetY + (maxY - ys[i]) * scale,
    }));
}
