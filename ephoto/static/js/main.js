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
		var unzipProgress = document.createElement("progress");
		var fileList = document.getElementById("file-list");

		function getDataBase64(entry, type, li) {
			model.getEntryFile(entry, type, function(blob) {
				var clickEvent = document.createEvent("MouseEvent");
				if (unzipProgress.parentNode){
					unzipProgress.parentNode.removeChild(unzipProgress);
				}
				unzipProgress.value = 0;
				unzipProgress.max = 0;

				var reader = new FileReader();
				reader.onload = function(e) {
					var img = document.getElementById("image");
					img.src = e.target.result;
				};
				reader.readAsDataURL(blob);
			}, function(current, total) {
				unzipProgress.value = current;
				unzipProgress.max = total;
				li.appendChild(unzipProgress);
			});
		}

		fileInput.addEventListener('change', function() {
			var fileName = fileInput.files[0].name,
				extensions = fileName.split('.'),
				extension = extensions[extensions.length -1];
			if(fileType[extension]){

				var li = document.createElement("li");
				var a = document.createElement("a");
				a.textContent = fileName;
				a.href = "#";
				a.addEventListener("click", function(event) {
					var reader = new FileReader();
					reader.onload = function(e) {
						var img = document.getElementById("image");
						img.src = e.target.result;
					};
					reader.readAsDataURL(fileInput.files[0]);
					
					event.preventDefault();
					return false;
				}, false);

				li.appendChild(a);
				fileList.appendChild(li);
			} else {
				model.getEntries(fileInput.files[0], function(entries) {
					fileList.innerHTML = "";
					entries.forEach(function(entry) {
						var tokens = entry.filename.split('.'),
							type = tokens[tokens.length - 1];

						if(fileType[type]){
							var li = document.createElement("li");
							var a = document.createElement("a");
							a.textContent = entry.filename;
							a.href = "#";
							a.addEventListener("click", function(event) {
								getDataBase64(entry, type, li);
								event.preventDefault();
								return false;
							}, false);
							li.appendChild(a);
							fileList.appendChild(li);
						}
					});
				});
			}
		}, false);
	})();

})(this);
