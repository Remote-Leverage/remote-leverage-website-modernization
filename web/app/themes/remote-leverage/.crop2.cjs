const sharp=require('sharp');
(async()=>{const[,,src,L,T,W,H,out,scale]=process.argv;
let p=sharp(src).extract({left:+L,top:+T,width:+W,height:+H});
if(scale)p=p.resize({width:Math.round(+W*+scale)});
await p.png().toFile(out);console.log(out);})();
