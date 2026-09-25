<div class="card">
	<div class="card-body pt-3">

        <div class="input-group">

			<div class="col-md-12 col-12 p-1">
				<div class="form-group {{ $errors->has('nombre') ? 'has-error' : ''}}">
					<label>Nombre: <span class="text-danger">*</span></label>
					<input type="text" name="nombre" class="form-control" maxlength="100" placeholder="Ej. Sencilla, Doble, Suite"
						value="{{  old('nombre') ?? $tipo_habitacion->nombre ?? '' }}">
					{!! $errors->first('nombre', '<p class="text-danger">:message</p>') !!}
				</div>
			</div>

		</div>

        <div class="input-group">

            <div class="col-md-6 col-12 p-1">
				<div class="form-group {{ $errors->has('capacidad') ? 'has-error' : ''}}">
					<label>Capacidad (huéspedes): <span class="text-danger">*</span></label>
					<input type="number" name="capacidad" class="form-control" min="1" max="20" step="1"
						value="{{  old('capacidad') ?? $tipo_habitacion->capacidad ?? '' }}">
					{!! $errors->first('capacidad', '<p class="text-danger">:message</p>') !!}
				</div>
			</div>

            <div class="col-md-6 col-12 p-1">
				<div class="form-group  {{ $errors->has('precio_base') ? 'has-error' : ''}}">
					<label>Precio base por noche (USD): <span class="text-danger">*</span></label>
					<input type="number" name="precio_base" class="form-control" min="0" step="0.01" placeholder="0.00"
						value="{{  old('precio_base') ?? $tipo_habitacion->precio_base ?? '' }}">
					{!! $errors->first('precio_base', '<p class="text-danger">:message</p>') !!}
				</div>
			</div>

		</div>

        <div class="input-group">

            <div class="col-md-12 col-12 p-1">
				<div class="form-group  {{ $errors->has('descripcion') ? 'has-error' : ''}}">
					<label for="descripcion">Descripción: </label>
					<textarea name="descripcion" id="descripcion" rows="5" cols="20" class="form-control" placeholder="Camas, vista, comodidades incluidas...">{{  old('descripcion') ?? $tipo_habitacion->descripcion ?? '' }}</textarea>
					{!! $errors->first('descripcion', '<p class="text-danger">:message</p>') !!}
				</div>
			</div>

		</div>

        <div class="input-group">

            <div class="col-md-12 col-12 p-1">
				<div class="form-group  {{ $errors->has('imagenes') || $errors->has('imagenes.*') ? 'has-error' : ''}}">
					<label for="imagenes">{{ $formMode === 'Editar' ? 'Agregar fotos:' : 'Fotos:' }} </label>
					<input type="file" name="imagenes[]" id="imagenes" class="form-control" accept="image/jpeg,image/png,image/webp" multiple>
					<small class="text-muted">Hasta 10 imágenes JPG, PNG o WEBP de máximo 4 MB cada una. La primera que suba será la foto principal.</small>
					{!! $errors->first('imagenes', '<p class="text-danger">:message</p>') !!}
					{!! $errors->first('imagenes.*', '<p class="text-danger">:message</p>') !!}
					<div id="previsualizacion" class="d-flex flex-wrap gap-2 mt-2"></div>
				</div>
			</div>

		</div>

	</div>
	<div class="card-footer">
		<div class="form-group">
		    <a href="{{ url('tipo_habitacion') }}" class="btn btn-danger" data-bs-toggle="tooltip" title="Atrás">
		        <i class="fas fa-arrow-left"></i> &nbsp;Atrás
		    </a>
		    <button class="btn btn-primary" type="submit" data-bs-toggle="tooltip" title="{{ $formMode === 'Editar' ? 'Editar' : 'Crear' }}">
		    	<i class="fas fa-save"></i>&nbsp;{{ $formMode === 'Editar' ? 'Editar' : 'Crear' }}
		    </button>
		</div>
	</div>
</div>

<script type="text/javascript">
	$(document).ready(function() {

        //Previsualizacion de las fotos seleccionadas antes de guardar
        $('#imagenes').on('change', function() {
            const contenedor = document.getElementById('previsualizacion');
            contenedor.innerHTML = '';

            Array.from(this.files).forEach(function(archivo) {
                if (!archivo.type.startsWith('image/')) return;

                const img = document.createElement('img');
                img.src = URL.createObjectURL(archivo);
                img.className = 'rounded border';
                img.style.width = '110px';
                img.style.height = '80px';
                img.style.objectFit = 'cover';
                img.onload = function() { URL.revokeObjectURL(img.src); };
                contenedor.appendChild(img);
            });
        });

    });
</script>
