(function(obj) {

	var requestFileSystem = obj.webkitRequestFileSystem || obj.mozRequestFileSystem || obj.requestFileSystem,
		fileType = {
			'jpg': 'image/jpeg',
			'png': 'image/png',
			'gif': 'image/gif'
		};

	zip.workerScriptsPath = '/ow_static/plugins/ephoto/js/lib/'

	function onerror(message) {
		alert(message);
	}

	function createTempFile(callback) {
		var tmpFilename = "tmp.dat";
		requestFileSystem(TEMPORARY, 4 * 1024 * 1024 * 1024, function(filesystem) {
			function create() {
				filesystem.root.getFile(tmpFilename, {
					create : true
				}, function(zipFile) {
					callback(zipFile);
				});
			}

			filesystem.root.getFile(tmpFilename, null, function(entry) {
				entry.remove(create, create);
			}, create);
		});
	}

	var model = (function() {
		var URL = obj.webkitURL || obj.mozURL || obj.URL;

		return {
			getEntries : function(file, onend) {
				zip.createReader(new zip.BlobReader(file), function(zipReader) {
					zipReader.getEntries(onend);
				}, onerror);
			},
			getEntryFile : function(entry, type, onend, onprogress) {
				var writer, zipFileEntry;

				function getData() {
					entry.getData(writer, function(blob) {
						onend(blob);
					}, onprogress);
				}

				writer = new zip.BlobWriter(fileType[type]);
				getData();
			}
		};
	})();

	(function() {
		var fileInput = document.getElementById("file-input");
		var fileList = document.getElementById("file-list");

		function getDataBase64(entry, type) {
			model.getEntryFile(entry, type, function(blob) {
				var clickEvent = document.createEvent("MouseEvent");

				var reader = new FileReader();
				reader.onload = function(e) {
					data.photos[entry.filename] = e.target.result;
				};
				reader.readAsDataURL(blob);
			});
		}

		fileInput.addEventListener('change', function() {
			try {
				var fileName = fileInput.files[0].name,
					extensions = fileName.split('.'),
					extension = extensions[extensions.length -1];

				data.photos = {};

				if(fileType[extension]){
					var reader = new FileReader();
					reader.onload = function(e) {
						data.photos[fileName] = e.target.result;
					};
					reader.readAsDataURL(fileInput.files[0]);
				} else {
					model.getEntries(fileInput.files[0], function(entries) {
						entries.forEach(function(entry) {
							var tokens = entry.filename.split('.'),
								type = tokens[tokens.length - 1];

							if(fileType[type]){
								getDataBase64(entry, type);
							}
						});
					});
				}
				data.ready = true;
			} catch (err) {
				console.log(err);
				data.ready = false;
			}
		}, false);
	})();

})(this);
