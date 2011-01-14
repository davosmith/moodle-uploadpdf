// Only used when viewing previous pages - for all interactive line drawing, see 'annotate.js'

function drawline(lsx, lsy, lex, ley, colour) {
    var linewidth = 3.0;
    var halflinewidth = linewidth * 0.5;
    var dims = $('pdfimg').getCoordinates();
    var coords = { sx: lsx, sy: lsy, ex: lex, ey: ley };

    coords.sx += dims.left;   coords.ex += dims.left;
    coords.sy += dims.top;    coords.ey += dims.top;

    if (coords.sx > coords.ex) { // Always go left->right
	var temp = coords.sx; coords.sx = coords.ex; coords.ex = temp;
	temp = coords.sy;     coords.sy = coords.ey; coords.ey = temp;
    }
    if (coords.sy < coords.ey) {
	var boundary = {x: (coords.sx-halflinewidth), y: (coords.sy-halflinewidth), w: (coords.ex+linewidth-coords.sx), h: (coords.ey+linewidth-coords.sy)};
	coords.sy = halflinewidth; coords.ey = boundary.h - halflinewidth;
    } else {
	var boundary = {x: (coords.sx-halflinewidth), y: (coords.ey-halflinewidth), w: (coords.ex+linewidth-coords.sx), h: (coords.sy+linewidth-coords.ey)};
	coords.sy = boundary.h - halflinewidth; coords.ey = halflinewidth;
    }
    coords.sx = halflinewidth; coords.ex = boundary.w - halflinewidth;
    var paper = Raphael(boundary.x, boundary.y, boundary.w+2, boundary.h+2);
    var line = paper.path("M "+coords.sx+" "+coords.sy+" L "+coords.ex+" "+coords.ey);
    line.attr("stroke-width", linewidth);

    var rgb;
    if (colour == "yellow") { rgb = "#ff0"; }
    else if (colour == "green") { rgb = "#0f0"; }
    else if (colour == "blue") { rgb = "#00f"; }
    else if (colour == "white") { rgb = "#fff"; }
    else if (colour == "black") { rgb = "#000"; }
    else { rgb = "#f00"; } // Red
    line.attr("stroke", rgb);
}

window.addEvent('domready', function() {
    new Asset.css('style/annotate.css');
});