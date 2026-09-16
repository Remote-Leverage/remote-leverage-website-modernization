const sharp=require('sharp');
(async()=>{const[,,src,L,T,W,H,out,scale]=process.argv;
const m=await sharp(src).metadata();
const t=Math.max(0,+T), h=Math.min(+H, m.height-t);
let p=sharp(src).extract({left:+L,top:t,width:Math.min(+W,m.width-+L),height:h});
if(scale)p=p.resize({width:Math.round(Math.min(+W,m.width-+L)*+scale)});
await p.png().toFile(out);console.log(out,`${h}px tall`);})();
