/** Convert reviewed Markdown to Gutenberg blocks without typographic rewriting. */
import fs from 'node:fs';
import path from 'node:path';
import {pathToFileURL} from 'node:url';
const moduleRoot = process.env.SDC_NODE_MODULES || path.join(process.env.USERPROFILE || '', '.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules');
const {marked} = await import(pathToFileURL(path.join(moduleRoot, 'marked/lib/marked.esm.js')).href);
const escape = s => String(s).replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;');
const block = (name, attrs, html) => `<!-- wp:${name}${Object.keys(attrs).length?' '+JSON.stringify(attrs):''} -->\n${html.trim()}\n<!-- /wp:${name} -->`;
function render(tokens, code) {
  return tokens.map(t => {
    if (t.type === 'space') return '';
    if (t.type === 'heading') return block('heading',{level:t.depth},`<h${t.depth} class="wp-block-heading">${marked.parseInline(t.text)}</h${t.depth}>`);
    if (t.type === 'paragraph') {
      const images = (t.tokens || []).filter(x=>x.type==='image');
      if (images.length === 1 && t.tokens.length === 1) {
        return block('image',{id:`__SDC_ID_${code}_INFOGRAPHIC__`,sizeSlug:'full',linkDestination:'none',className:'sd-editorial-infographic'},`<figure class="wp-block-image size-full sd-editorial-infographic"><img src="__SDC_ASSET_${code}_INFOGRAPHIC__" alt="${escape(images[0].text)}" class="wp-image-__SDC_ID_${code}_INFOGRAPHIC__"/></figure>`);
      }
      const isTrap = /^\s*(?:\*\*)?Pułapka:/i.test(t.text);
      return block('paragraph',isTrap?{className:'sd-editorial-trap'}:{},`<p${isTrap?' class="sd-editorial-trap"':''}>${marked.parseInline(t.text)}</p>`);
    }
    if (t.type === 'blockquote') {
      const text=t.text.toLocaleLowerCase('pl');
      const kind=/^\s*(?:\*\*)?ramka:\s*jak chronić pracownika/.test(text)?'employee':/^\s*(?:\*\*)?ramka:\s*jak chronić pracodawcę/.test(text)?'employer':'neutral';
      const cls='sd-editorial-box sd-editorial-box--'+kind;
      return block('quote',{className:cls},`<blockquote class="wp-block-quote ${cls}">${render(t.tokens,code)}</blockquote>`);
    }
    if (t.type === 'table') {
      const parsed=marked.parser([t]).replaceAll('<th>','<th scope="col">').replace(/<th align=/g,'<th scope="col" align=');
      return block('table',{className:'sd-editorial-table',hasFixedLayout:false},`<figure class="wp-block-table sd-editorial-table">${parsed}</figure>`);
    }
    if (t.type === 'list') {
      const tag=t.ordered?'ol':'ul';
      const attrs=t.ordered?{ordered:true}:{};
      if(t.ordered && t.start!==1) attrs.start=t.start;
      const items=t.items.map(item=>{
        let content=marked.parser(item.tokens).trim();
        // A list item with one paragraph is stored as inline content by core/list-item.
        if(/^<p>[^]*<\/p>$/.test(content) && (content.match(/<p>/g)||[]).length===1) content=content.slice(3,-4);
        if(item.task) content=`${item.checked?'☑':'☐'} ${content}`;
        return block('list-item',{},`<li>${content}</li>`);
      }).join('\n');
      return block('list',attrs,`<${tag}${t.ordered&&t.start!==1?' start="'+t.start+'"':''} class="wp-block-list">${items}</${tag}>`);
    }
    if(t.type==='hr') return block('separator',{},'<hr class="wp-block-separator has-alpha-channel-opacity"/>');
    if(t.type==='text') return block('paragraph',{},'<p>'+marked.parseInline(t.text)+'</p>');
    // Explicit HTML/code is preserved, not interpreted as an instruction.
    return block('html',{},marked.parser([t]));
  }).filter(Boolean).join('\n\n');
}
const payload=JSON.parse(fs.readFileSync(0,'utf8'));
const result=payload.map(p=>({code:p.code,content:render(marked.lexer(p.markdown,{gfm:true,breaks:false}),p.code),lead_html:marked.parseInline(p.lead)}));
process.stdout.write(JSON.stringify(result));
