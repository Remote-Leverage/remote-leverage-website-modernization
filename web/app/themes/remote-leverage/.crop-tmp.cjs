const sharp = require('sharp');
(async () => {
  const [,, src, top, height, out, scale] = process.argv;
  const img = sharp(src);
  const meta = await img.metadata();
  const t = Math.max(0, parseInt(top));
  const h = Math.min(parseInt(height), meta.height - t);
  let p = img.extract({ left: 0, top: t, width: meta.width, height: h });
  if (scale) p = p.resize({ width: Math.round(meta.width * parseFloat(scale)) });
  await p.png().toFile(out);
  console.log(out, meta.width, h);
})();
