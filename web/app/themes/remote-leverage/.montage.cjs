const sharp=require('sharp');
(async()=>{
  const files=process.argv.slice(3);
  const out=process.argv[2];
  const tiles=await Promise.all(files.map(async f=>({f,buf:await sharp(f).resize({width:220,height:280,fit:'contain',background:{r:240,g:240,b:245}}).png().toBuffer()})));
  const cols=Math.min(6,tiles.length), rows=Math.ceil(tiles.length/cols);
  const canvas=sharp({create:{width:cols*220,height:rows*300,channels:3,background:{r:255,g:255,b:255}}});
  const comp=tiles.map((t,i)=>({input:t.buf,left:(i%cols)*220,top:Math.floor(i/cols)*300}));
  await canvas.composite(comp).png().toFile(out);
  console.log(files.map((f,i)=>`${i}: ${f.split('/').pop()}`).join('\n'));
})();
