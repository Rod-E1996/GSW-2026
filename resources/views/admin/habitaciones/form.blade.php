<div class="card">
	<div class="card-body pt-3">

        <div class="input-group">

			<div class="col-md-6 col-12 p-1">
				<div class="form-group {{ $errors->has('numero') ? 'has-error' : ''}}">
					<label>Número: <span class="text-danger">*</span></label>
					<input type="text" name="numero" class="form-control" maxlength="10" placeholder="Ej. 101, 205, A-3"
						value="{{  old('numero') ?? $habitacion->numero ?? '' }}">
					{!! $errors->first('numero', '<p class="text-danger">:message</p>') !!}
				</div>
			</div>

            <div class="col-md-6 col-12 p-1">
				<div class="form-group {{ $errors->has('piso') ? 'has-error' : ''}}">
					<label>Piso: <span class="text-danger">*</span></label>
					<input type="number" name="piso" class="form-control" min="0" max="50" step="1"
						value="{{  old('piso') ?? $habitacion->piso ?? '' }}">
					{!! $errors->first('piso', '<p class="text-danger">:message</p>') !!}
				</div>
			</div>

		</div>

        <div class="input-group">

            <div class="col-md-6 col-12 p-1">
				<div class="form-group  {{ $errors->has('tipo_habitacion_id') ? 'has-error' : ''}}">
					<label>Tipo de habitación: <span class="text-danger">*</span></label>
					<select class="form-control select2" name="tipo_habitacion_id" id="tipo_habitacion_id" style="width: 100%;">
						<option value="">Seleccione...</option>
						@foreach($tipos_habitacion as $value)
							<option value="{{ $value->id }}">{{ $value->nombre }} ({{ $value->capacidad }} {{ $value->capacidad == 1 ? 'huésped' : 'huéspedes' }}, ${{ number_format($value->precio_base, 2) }})</option>
						@endforeach
					</select>
					{!! $errors->first('tipo_habitacion_id', '<p class="text-danger">:message</p>') !!}
				</div>
			</div>

            <div class="col-md-6 col-12 p-1">
				<div class="form-group  {{ $errors->has('estado_habitacion') ? 'has-error' : ''}}">
					<label>Estado: <span class="text-danger">*</span></label>
					<select class="form-control select2" name="estado_habitacion" id="estado_habitacion" style="width: 100%;">
						@foreach($estados as $valor => $estado)
							<option value="{{ $valor }}">{{ $estado['nombre'] }}</option>
						@endforeach
					</select>
					{!! $errors->first('estado_habitacion', '<p class="text-danger">:message</p>') !!}
				</div>
			</div>

		</div>

        <div class="input-group">

            <div class="col-md-12 col-12 p-1">
				<div class="form-group  {{ $errors->has('descripcion') ? 'has-error' : ''}}">
					<label for="descripcion">Descripción: </label>
					<textarea name="descripcion" id="descripcion" rows="4" cols="20" class="form-control" placeholder="Vista, ubicación, detalles particulares de esta habitación...">{{  old('descripcion') ?? $habitacion->descripcion ?? '' }}</textarea>
					{!! $errors->first('descripcion', '<p class="text-danger">:message</p>') !!}
				</div>
			</div>

		</div>

	</div>
	<div class="card-footer">
		<div class="form-group">
		    <a href="{{ url('habitacion') }}" class="btn btn-danger" data-bs-toggle="tooltip" title="Atrás">
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

        // precargar datos
        let tipo_habitacion_id = '{{ old("tipo_habitacion_id") ?? $habitacion->tipo_habitacion_id ?? '' }}';
        if( tipo_habitacion_id != '' ){
            $("#tipo_habitacion_id").val(tipo_habitacion_id);
        }

        let estado_habitacion = '{{ old("estado_habitacion") ?? $habitacion->estado_habitacion ?? 1 }}';
        $("#estado_habitacion").val(estado_habitacion);

        $('.select2').select2({ theme: 'bootstrap4', width: '100%', dropdownAutoWidth: true });

        $(document).on('select2:open', () => {
            document.querySelector('.select2-search__field').focus();
        });

    });
</script>
