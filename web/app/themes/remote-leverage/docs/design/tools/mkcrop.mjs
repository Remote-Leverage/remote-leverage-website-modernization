import { decode, crop, encode } from './pnglib.mjs';
const [file, tag, ...ranges] = process.argv.slice(2);
const img = decode(file);
for (const r of ranges) {
  const [y0,y1,sc] = r.split(':').map(Number);
  const c = crop(img, y0, y1, sc||1);
  const out = `/private/tmp/claude-501/-Users-adriansalvatori-Documents-projects-rl-remoteleverage-v2-web-app-themes-remote-leverage/16699a9e-e5ca-4888-8845-5971c17339e4/scratchpad/${tag}-${y0}-${y1}.png`;
  encode(out, c.w, c.h, c.ch, c.data);
  console.log(out, `${c.w}x${c.h}`);
}
