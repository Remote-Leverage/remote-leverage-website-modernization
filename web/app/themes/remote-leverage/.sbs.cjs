const sharp=require('sharp');
(async()=>{
  const [,,a,b,out]=process.argv;
  const A=await sharp(a).resize({width:660}).toBuffer();
  const B=await sharp(b).resize({width:660}).toBuffer();
  const ma=await sharp(A).metadata(), mb=await sharp(B).metadata();
  const h=Math.max(ma.height,mb.height);
  await sharp({create:{width:1340,height:h,channels:3,background:{r:255,g:255,b:255}}})
    .composite([{input:A,left:0,top:0},{input:B,left:680,top:0}]).png().toFile(out);
  console.log(out,'left=comp right=render');
})();
