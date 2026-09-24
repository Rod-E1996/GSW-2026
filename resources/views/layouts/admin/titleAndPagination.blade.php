<div class="pagetitle">
    <h1>{{ $title ?? '' }}</h1>
    @if( !empty( $breadcrumb ) && $breadcrumb == true )
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.html">Home</a></li>
                <li class="breadcrumb-item">Pages</li>
                <li class="breadcrumb-item active">Blank</li>
            </ol>
        </nav>
    @endif
</div>
