@extends('negocios.template')
@section('navbar')

<li>
    <a href="{{ URL::route('negocios::index') }}"><span class="glyphicon glyphicon-list-alt"></span> Listagem</a>
</li>

@endsection

@section('title')
Negócio # {{ $model->codnegocio }}
@endsection

@section('body')
<div class="row">
    <div class="col-sm-8">
        <h3>Produtos</h3>
        <form class="form-inline">
            <div class="form-group">
                <label class="sr-only" for="exampleInputAmount">Quantidade</label>
                <div class="input-group">
                    <div class="input-group-addon">Quantidade</div>
                    <input type="text" class="form-control" id="exampleInputAmount" placeholder="Amount">
                </div>
            </div>
            <div class="form-group">
                <label class="sr-only" for="exampleInputAmount">Código</label>
                <div class="input-group">
                    <div class="input-group-addon">Código</div>
                    <input type="text" class="form-control" id="exampleInputAmount" placeholder="Amount">
                </div>
            </div>
            <button type="submit" class="btn btn-default">Adicionar</button>
        </form>
    </div>
    <div class="col-sm-4"></div>
</div>
@endsection
