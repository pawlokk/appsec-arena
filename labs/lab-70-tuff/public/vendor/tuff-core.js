;(function(w) {
  var tuffData = w.tuffData || {};
  var m = /build=([^&]+)/.exec(w.location ? w.location.search : '');
  var build = m && decodeURIComponent(m[1]);
  if (build) {
    var ui = { buildPath: build };
    document.write('<script src="' + ui.buildPath + '"><\/script>');
  }
  w.tuffData = tuffData;
})(window);
