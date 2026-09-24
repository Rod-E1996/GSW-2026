<div class="card">
	<div class="card-body pt-3">

        <div class="input-group">

			<div class="col-md-12 col-12 p-1">
				<div class="form-group {{ $errors->has('nombre') ? 'has-error' : ''}}">
					<label>Nombre: <span class="text-danger">*</span></label>
					<input type="text" name="nombre" class="form-control"
						value="{{  old('nombre') ?? $ejemplo->nombre ?? '' }}">
					{!! $errors->first('nombre', '<p class="text-danger">:message</p>') !!}
				</div>
			</div>

		</div>

        <div class="input-group">

            <div class="col-md-6 col-12 p-1">
				<div class="form-group {{ $errors->has('telefono') ? 'has-error' : ''}}">
					<label>Telefono: <span class="text-danger">*</span></label>
					<input type="text" name="telefono" class="form-control" id="telefono"
						value="{{  old('telefono') ?? $ejemplo->telefono ?? '' }}">
					{!! $errors->first('telefono', '<p class="text-danger">:message</p>') !!}
				</div>
			</div>

            <div class="col-md-6 col-12 p-1">
				<div class="form-group  {{ $errors->has('fecha') ? 'has-error' : ''}}">
					<label>Fecha: <span class="text-danger">*</span></label>
					<input type="date" name="fecha" class="form-control"
						value="{{  old('fecha') ?? $ejemplo->fecha ?? '' }}">
					{!! $errors->first('fecha', '<p class="text-danger">:message</p>') !!}
				</div>
			</div>

		</div>

        <div class="input-group">

            <div class="col-md-6 col-12 p-1">
				<div class="form-group  {{ $errors->has('cantidad') ? 'has-error' : ''}}">
					<label>Cantidad: <span class="text-danger">*</span></label>
					<input type="text" name="cantidad" class="form-control" id="cantidad"
						value="{{  old('cantidad') ?? $ejemplo->cantidad ?? '' }}">
					{!! $errors->first('cantidad', '<p class="text-danger">:message</p>') !!}
				</div>
			</div>

            <div class="col-md-6 col-12 p-1">
				<div class="form-group  {{ $errors->has('porcentaje') ? 'has-error' : ''}}">
					<label>Porcentaje: <span class="text-danger">*</span></label>
					<input type="number" name="porcentaje" class="form-control"
						value="{{  old('porcentaje') ?? $ejemplo->porcentaje ?? '' }}" >
					{!! $errors->first('porcentaje', '<p class="text-danger">:message</p>') !!}
				</div>
			</div>

		</div>

        <div class="input-group">

            <div class="col-md-6 col-12 p-1">
				<div class="form-group  {{ $errors->has('select_quemado') ? 'has-error' : ''}}">
					<label>Select quemado: <span class="text-danger">*</span></label>
					<select class="form-control select2" name="select_quemado" id="select_quemado" style="width: 100%;">
						<option value="">Seleccione...</option>
						<option value="1" {{$formMode === 'Editar' ? ($ejemplo->select_quemado == 1  ? 'selected' : '') : ''}}>Dato 1</option>
						<option value="2" {{$formMode === 'Editar' ? ($ejemplo->select_quemado == 2  ? 'selected' : '') : ''}}>Dato 2</option>
						<option value="3" {{$formMode === 'Editar' ? ($ejemplo->select_quemado == 3  ? 'selected' : '') : ''}}>Dato 3</option>
					</select>
					{!! $errors->first('select_quemado', '<p class="text-danger">:message</p>') !!}
				</div>
			</div>

            <div class="col-md-6 col-12 p-1">
				<div class="form-group  {{ $errors->has('auditar_accion_id') ? 'has-error' : ''}}">
					<label>Auditar Acciones: <span class="text-danger">*</span></label>
					<select class="form-control select2" name="auditar_accion_id" id="auditar_acciones" style="width: 100%;">
						<option value="">Seleccione...</option>
						@foreach($auditar_acciones as $value)
							<option value="{{ $value->id }}" {{$formMode === 'Editar' ? ($ejemplo->auditar_accion_id == $value->id  ? 'selected' : '') : ''}}>{{ $value->nombre }}</option>
						@endforeach
					</select>
					{!! $errors->first('auditar_accion_id', '<p class="text-danger">:message</p>') !!}
				</div>
			</div>

		</div>

        <div class="input-group">

            <div class="col-md-12 col-12 p-1">
				<div class="form-group  {{ $errors->has('descripcion') ? 'has-error' : ''}}">
					<label for="descripcion">Descripción: </label>
					<textarea name="descripcion" rows="5" cols="20" class="form-control">{{  old('descripcion') ?? $ejemplo->descripcion ?? '' }}</textarea>
					{!! $errors->first('descripcion', '<p class="text-danger">:message</p>') !!}
				</div>
			</div>

		</div>

	</div>
	<div class="card-footer">
		<div class="form-group">
		    <a href="{{ url('ejemplo') }}" class="btn btn-danger" data-bs-toggle="tooltip" title="Atrás">
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
        let select_quemado = '{{ old("select_quemado") ?? $ejemplo->select_quemado ?? '' }}';
        if( select_quemado != '' && select_quemado != ' ' ){
            $("#select_quemado").val(select_quemado);
        }

		let auditar_acciones = '{{ old("auditar_accion_id") ?? $ejemplo->auditar_accion_id ?? '' }}';
        if( auditar_acciones != '' && auditar_acciones != ' ' ){
            $("#auditar_acciones").val(auditar_acciones);
        }

        //Mascara de telefono
        $('#telefono').mask('9999-9999');
        $('#cantidad').mask('999999.99');

        $('.select2').select2({ theme: 'bootstrap4', width: '100%', dropdownAutoWidth: true });

        $(document).on('select2:open', () => {
            document.querySelector('.select2-search__field').focus();
        });

    });
</script>
