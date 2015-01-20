var phoPlugin = ActiveBuild.UiPlugin.extend({
  id: 'build-pho-errors',
  css: 'col-lg-6 col-md-12 col-sm-12 col-xs-12',
  title: 'Pho',
  lastData: null,
  displayOnUpdate: false,
  box: true,
  rendered: false,

  register: function() {
    var self = this;
    var query = ActiveBuild.registerQuery('pho-data', -1, {key: 'pho-data'});

    $(window).on('pho-data', function(data) {
      self.onUpdate(data);
    });

    $(window).on('build-updated', function() {
      if (!self.rendered) {
        self.displayOnUpdate = true;
        query();
      }
    });
  },

  render: function() {
    return $('<div id="pho-metadata" style="padding: 0 10px">' + Lang.get('pending') +
    '</div><table class="table" id="pho-data">' +
    '<thead>' +
    '<tr>' +
    '   <th></th>' +
    '</tr>' +
    '</thead><tbody></tbody></table>');
  },

  onUpdate: function(e) {
    if (!e.queryData)
      return;

    this.rendered = true;
    this.lastData = e.queryData;

    var tests = this.lastData[0].meta_value;
    var tbody = $('#pho-data tbody');
    tbody.empty();

    $('#pho-metadata').html(tests.metadata.seconds + '<br>' + tests.metadata.specData);

    for (var i=0, l=tests.expectations.length; i<l; i++) {
      var expectation = tests.expectations[i];
      var html = '<td><b>' + expectation.d + '</b><br>' +
      expectation.e + '<br><em>';

      var file = expectation.f;
      var index = 0;
      var DS = file[0] == '/'? '/': '\\';

      index = file.indexOf('PHPCI' + DS + 'build' + DS);
      file = file.substr(index + 12);
      file = file.split(DS).slice(1).join(DS);

      html += file + '</em></td>';

      var tr = document.createElement('tr');
      tr.className = 'danger';
      tr.innerHTML = html;
      tbody.append(tr);
    }

    $('#build-pho-errors').show();
  }
});

ActiveBuild.registerPlugin(new phoPlugin());
