<!doctype html>
<html>
	<head>
		<title></title>
		<meta charset="utf8">
		<link href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u" crossorigin="anonymous">
		<link href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css" rel="stylesheet" integrity="sha384-wvfXpqpZZVQGK6TAh5PVlGOfQNHSoD2xbE+QkPxCAFlNEevoEH3Sl0sibVcOQVnN" crossorigin="anonymous">
		<link href="css/mystyle.css" rel="stylesheet" >
	<body>

			<input type="file" style="position:absolute; top: -100px" id="picker" onchange="picked_file(this.files[0])">
			<div class="container">
				<div class="row">
					<div class="col-lg-12 text-center" style="margin-top:20px;"></div>
						<div class="col-lg-8">
								<h2 class="text-center">Whiteboard</h2>
								<canvas id='c' class="" width="750" height="300"></canvas>
							
								<div class="col-lg-6"><button class="btn-block btn-primary btn" onclick="open_picker()">Upload</button></div>
								<div class="col-lg-6"><button class="btn-block btn-danger btn clear"  onclick="reset_canvas()">Clear</button></div>
							
						</div>
					<div class="col-lg-4"><h2 class="text-center">Text Output</h2>
						<div class="output">
								<div id="output">
								  <textarea id="text" class="form-control" ></textarea>
								</div>
							</div>
					</div>
				</div>
			
			
				
				
		</div>
		<script src="ocrad.js"></script>
		<script src="http://ajax.googleapis.com/ajax/libs/webfont/1/webfont.js"></script> 
		<script src="https://code.jquery.com/jquery-3.2.1.js" integrity="sha256-DZAnKJ/6XZ9si04Hgrsxu/8s717jcIzLy3oi35EouyE=" crossorigin="anonymous"></script>
		<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js" integrity="sha384-Tc5IQib027qvyjSMfHjOMaLkfuWVxZxUPnCJA7l2mCWNIpG9mGCD8wGNIcPD7Txa" crossorigin="anonymous"></script>


		<script>
		
			var c = document.getElementById('c'),
				o = c.getContext('2d');

			function reset_canvas(){
				o.fillStyle = 'white'
				o.fillRect(0, 0, c.width, c.height)
				o.fillStyle = 'black'
				
			}
			
			

			// here's a really simple little drawing app so people can try their luck at
			// the lottery that is offline handwriting recognition
			var drag = false, lastX, lastY;
			c.onmousedown = function(e){ drag = true; lastX = 0; lastY = 0; e.preventDefault(); c.onmousemove(e) }
			c.onmouseup   = function(e){ drag = false; e.preventDefault(); runOCR() }
			c.onmousemove = function(e){
				e.preventDefault()
				var rect = c.getBoundingClientRect();
				var r = 5;

				function dot(x, y){
					o.beginPath()
					o.moveTo(x + r, y)
					o.arc(x, y, r, 0, Math.PI * 2)
					o.fill()
				}
				if(drag){
					var x = e.clientX - rect.left, 
						y = e.clientY - rect.top;
					
					if(lastX && lastY){
						var dx = x - lastX, dy = y - lastY;
						var d = Math.sqrt(dx * dx + dy * dy);
						for(var i = 1; i < d; i += 2){
							dot(lastX + dx / d * i, lastY + dy / d * i)
						}
					}
					dot(x, y)

					lastX = x;
					lastY = y;
				}
			}


			document.body.ondragover = function(){ document.body.className = 'dragging'; return false }
			document.body.ondragend = function(){ document.body.className = ''; return false }
			document.body.onclick = function(){document.body.className = '';}
			document.body.ondrop = function(e){
				e.preventDefault();
				document.body.className = '';
				picked_file(e.dataTransfer.files[0]);
				return false;
			}

			function open_picker(){
				var e = document.createEvent("MouseEvents");	
				e.initEvent('click', true, true);
				document.getElementById('picker').dispatchEvent(e);
			}

			function picked_file(file){
				if(!file) return;
				// document.getElementById("output").className = 'processing'

				var ext = file.name.split('.').slice(-1)[0];
				var reader = new FileReader();

				if(file.type == "image/x-portable-bitmap" || ext == 'pbm' || ext == 'pgm' || ext == 'pnm' || ext == 'ppm'){
					reader.onload = function(){
						reset_canvas();
						document.getElementById("text").innerHTML = 'Recognizing Text... This may take a while...'
						o.font = '30px sans-serif'
						o.fillText('No previews for NetPBM format.', 50, 100);
						runOCR(new Uint8Array(reader.result), true);
					}
					reader.readAsArrayBuffer(file)
				}else{
					reader.onload = function(){
						var img = new Image();
						img.src = reader.result;
						img.onerror = function(){
							reset_canvas();
							o.font = '30px sans-serif'
							o.fillText('Error: Invalid Image ' + file.name, 50, 100);
						}
						img.onload = function(){
							document.getElementById("text").innerHTML = 'Recognizing Text... This may take a while...'
							reset_canvas();
							var rat = Math.min(c.width / img.width, c.height / img.height);
							o.drawImage(img, 0, 0, img.width * rat, img.height * rat)
							var tmp = document.createElement('canvas')
							tmp.width = img.width;
							tmp.height = img.height;
							var ctx = tmp.getContext('2d')
							ctx.drawImage(img, 0, 0)
							var image_data = ctx.getImageData(0, 0, tmp.width, tmp.height);
							runOCR(image_data, true)
						}
						
					}
					reader.readAsDataURL(file)
				}
				
			}

			var lastWorker;
			var worker = new Worker('worker.js')
			function runOCR(image_data, raw_feed){
				document.getElementById("output").className = 'processing'
				worker.onmessage = function(e){

					document.getElementById("output").className = ''
					
					if('innerText' in document.getElementById("text")){
						document.getElementById("text").innerText = e.data
					}else{
						document.getElementById("text").textContent = e.data	
					}
					//document.getElementById('timing').innerHTML = 'recognition took ' + ((Date.now() - start)/1000).toFixed(2) + 's';
				}
				var start = Date.now()
				if(!raw_feed){
					image_data = o.getImageData(0, 0, c.width, c.height);	
				}

				worker.postMessage(image_data)
				lastWorker = worker;
			}



			reset_canvas()


			
			var fonts = ['Droid Sans', 'Philosopher', 'Alegreya Sans', 'Chango', 'Coming Soon', 'Allan', 'Cardo', 'Bubbler One', 'Bowlby One SC', 'Prosto One', 'Rufina', 'Cantora One', 'Denk One', 'Play', 'Architects Daughter', 'Nova Square', 'Inder', 'Gloria Hallelujah', 'Telex', 'Comfortaa', 'Merienda', 'Boogaloo', 'Krona One', 'Orienta', 'Sofadi One', 'Source Sans Pro', 'Revalia', 'Overlock', 'Kelly Slab', 'Rye', 'Butcherman', 'Lato', 'Milonga', 'Aladin', 'Princess Sofia', 'Audiowide', 'Italiana', 'Michroma', 'Cabin Condensed', 'Jura', 'Marko One', 'PT Mono', 'Bubblegum Sans', 'Amaranth']
			

			function fisher_yates(a) {
				for (var i = a.length - 1; i > 0; i--) {
					var j = Math.floor(Math.random() * (i + 1));
					var temp = a[i]; a[i] = a[j]; a[j] = temp;
				}
			}

			fisher_yates(fonts);
			

			

			o.font = '30px sans-serif'
			//o.fillText("Jaime Ramos jr!", 50, 100);
			runOCR();
		</script>
	</body>
</html>
