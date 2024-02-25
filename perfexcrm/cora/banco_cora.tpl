
<!-- Arquivo  - Template para o gateway de pagamento do Banco Cora -->

<form action="processar_pagamento.php" method="POST">
    <input type="hidden" name="valor" value="100.00">
    <input type="hidden" name="cliente_id" value="123">
    <input type="hidden" name="descricao" value="Pagamento do Pedido #12345">
    
    <label for="numero_cartao">Número do Cartão:</label>
    <input type="text" id="numero_cartao" name="numero_cartao" placeholder="1234 5678 9012 3456">

    <label for="cvv">CVV:</label>
    <input type="text" id="cvv" name="cvv" placeholder="123">

    <label for="validade">Validade:</label>
    <input type="text" id="validade" name="validade" placeholder="MM/AA">

    <button type="submit">Pagar Agora</button>
</form>
