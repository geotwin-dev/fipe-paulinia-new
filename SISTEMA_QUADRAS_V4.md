# Sistema Simplificado v4.1 - Marcadores e Quadras (Busca Ampla)

## Resumo da Nova Implementação

Sistema **ultra simplificado** focando apenas em:
- ✅ **Marcadores** (pontos dos imóveis filtrados)
- ✅ **Quadras** (polígonos das quadras que contêm os imóveis)
- ❌ **Quarteirões, Lotes, Lotes da Prefeitura** (removidos completamente)

## Principais Mudanças

### 1. **Busca de Quadras no PHP**

**`consultas/buscar_coordenadas.php`:**
```sql
-- Nova consulta AMPLA para quadras (sem filtro de camada, como index3)
SELECT * FROM desenhos 
WHERE tipo = 'poligono' 
AND (
    (quarteirao = ? AND quadra = ?) OR
    (quarteirao = ? AND quadra = ?) OR
    ...
)
ORDER BY quarteirao, quadra
```

### 2. **Lógica de Identificação de Quadras**
```php
// Coletar combinações quarteirão/quadra dos marcadores encontrados
foreach ($coordenadas_encontradas as $marcador) {
    $quarteirao = $marcador['quarteirao'];
    $quadra = $marcador['quadra'];
    
    // Chave única para identificar cada quadra
    $chave_quadra = $quarteirao . '_' . $quadra;
    $quadras_unicas[$chave_quadra] = [
        'quarteirao' => $quarteirao,
        'quadra' => $quadra
    ];
}
```

### 3. **Função de Relevância Simples**
```php
function verificarRelevanciaPoligono($poligono, $camada, $combinacoes_marcadores_encontrados, $coordenadas_encontradas) {
    // ABORDAGEM AMPLA: Aceita qualquer polígono que tenha quarteirão/quadra dos marcadores
    $quarteirao = $poligono['quarteirao'];
        $quadra = $poligono['quadra'];
        
        foreach ($coordenadas_encontradas as $marcador) {
            if ($marcador['quarteirao'] === $quarteirao && $marcador['quadra'] === $quadra) {
                return true;
            }
        }
    }
    
    return false;
}
```

### 4. **JavaScript Atualizado**

**`consultas/mapa_plot.php`:**
- Função `criarPoligonosQuadras()`
- Estilo vermelho para quadras (#FF0000)
- Filtro: aceita **qualquer camada** desde que seja relevante
- Debug simplificado apenas para quadras

## Fluxo do Sistema

1. **Buscar Marcadores**: Por quarteirão/quadra/lote
2. **Identificar Quadras**: Extrai combinações únicas quarteirão+quadra
3. **Buscar Polígonos**: Busca APENAS quadras dessas combinações
4. **Aplicar Relevância**: Só aceita quadras com marcadores
5. **Plotar no Mapa**: Marcadores + quadras relevantes

## Resultado Esperado

**Console deve mostrar:**
```
Encontradas X coordenadas (marcadores)
Encontrados Y polígonos (quadras)
=== RESUMO SIMPLES ===
Quadras criadas: Y
Quadras rejeitadas: Z
```

## Estilo Visual

- **Marcadores**: Pontos coloridos conforme status dos imóveis
- **Quadras**: Polígonos vermelhos com transparência
  - Cor: `#FF0000`
  - Transparência: `0.25`
  - Borda: `3px`

## Benefícios

- 🎯 **Foco**: Apenas o essencial (imóveis + contexto das quadras)
- ⚡ **Performance**: Consulta SQL mais eficiente
- 🔍 **Precisão**: Só mostra quadras que realmente têm imóveis filtrados
- 🐛 **Simplicidade**: Menos código, menos pontos de falha
- 📱 **Clareza**: Interface visual mais limpa

## Como Testar

1. Execute uma busca no sistema
2. Console deve mostrar: `Encontrados X polígonos` > 0
3. Use botão "Debug Polígonos" para verificar detalhes
4. Visualizar marcadores + quadras vermelhas no mapa

Este sistema mantém apenas o essencial: **onde estão os imóveis** (marcadores) e **em que quadras eles estão** (contexto visual)!
