import { decode, encode } from './pnglib.mjs';
// Nearest-neighbour magnify of a region, so glyph shapes can be compared by eye.
const [file, out, y0s, y1s, x0s, x1s, zs] = process.argv.slice(2);
const img = decode(file);
const y0=+y0s, y1=+y1s, x0=+x0s, x1=+x1s, z=+(zs||4);
const w=(x1-x0)*z, h=(y1-y0)*z;
const buf = Buffer.alloc(w*h*img.ch);
for (let y=0;y<h;y++) for (let x=0;x<w;x++){
  const sy=y0+Math.floor(y/z), sx=x0+Math.floor(x/z);
  const si=(sy*img.w+sx)*img.ch, di=(y*w+x)*img.ch;
  for(let c=0;c<img.ch;c++) buf[di+c]=img.data[si+c];
}
encode(out, w, h, img.ch, buf);
console.log(out, `${w}x${h}`);
