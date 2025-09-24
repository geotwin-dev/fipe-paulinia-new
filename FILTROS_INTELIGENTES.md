# Sistema de Filtros Inteligentes para Polígonos

## Resumo das Melhorias Implementadas

Foi implementado um sistema de filtros inteligentes que otimiza a exibição de polígonos (lotes, quadras, quarteirões e lotes da prefeitura) baseado nos marcadores realmente encontrados nos dados filtrados.

## Funcionalidades Implementadas

### 1. Filtro Inteligente para Polígonos do Banco (buscar_coordenadas.php)

- **Filtro por Relevância**: Os polígonos são marcados como "relevantes" ou não baseado em sua relação com os marcadores encontrados
- **Estratégias de Filtragem**:
  - **Lotes**: Devem ter marcador exato no mesmo quarteirao/quadra/lote
  - **Quadras**: Devem ter pelo menos um marcador na mesma quadra
  - **Quarteirões**: Devem ter pelo menos um marcador no mesmo quarteirão

### 2. Filtro Geoespacial para Lotes da Prefeitura (mapa_plot.php)

- **Filtro por Proximidade**: Calcula a distância entre o centro do lote da prefeitura e os marcadores encontrados
- **Raio de Proximidade**: ~500 metros (0.005 graus)
- **Fallback**: Se não há coordenadas de marcadores, usa filtro por quarteirão
- **Performance**: Só carrega lotes relevantes, reduzindo significativamente o uso de memória

### 3. Otimizações de Performance

- **Busca Seletiva**: Apenas polígonos relacionados aos marcadores são exibidos
- **Estatísticas Detalhadas**: Mostra quantos polígonos foram rejeitados pelo filtro
- **Log Inteligente**: Informações detalhadas sobre os critérios de filtragem

## Benefícios

### 🚀 Performance
- Redução drástica no número de polígonos carregados no mapa
- Menor uso de memória e processamento
- Carregamento mais rápido especialmente em consultas específicas

### 🎯 Precisão
- Exibe apenas os elementos realmente relevantes para os dados filtrados
- Elimina "ruído visual" de polígonos não relacionados
- Melhora a clareza da visualização

### 📊 Transparência
- Estatísticas claras sobre quantos elementos foram filtrados
- Logs detalhados para debugging e monitoramento
- Informações sobre os critérios utilizados

## Exemplos de Uso

### Cenário 1: Consulta Específica
- **Antes**: Mostrava todos os 50.000+ lotes da prefeitura da região
- **Depois**: Mostra apenas ~50 lotes próximos aos marcadores encontrados

### Cenário 2: Filtro por Quarteirão
- **Antes**: Mostrava todos os polígonos de lotes/quadras da quadrícula
- **Depois**: Mostra apenas polígonos que contêm marcadores dos dados filtrados

## Configurações

### Raio de Proximidade (Lotes da Prefeitura)
```javascript
const RAIO_PROXIMIDADE = 0.005; // ~500m
```

### Critérios de Relevância (Polígonos do Banco)
- **Lote**: Correspondência exata (quarteirao + quadra + lote)
- **Quadra**: Correspondência de quarteirão + quadra
- **Quarteirão**: Correspondência de quarteirão

## Estatísticas Exibidas

O sistema agora mostra:
- Número de polígonos criados por categoria
- Número de polígonos rejeitados pelo filtro
- Número de lotes da prefeitura carregados (filtrados)
- Informações sobre a base de filtragem (marcadores, quarteirões, quadrículas)

## Arquivos Modificados

1. **consultas/buscar_coordenadas.php**
   - Adicionada função `verificarRelevanciaPoligono()`
   - Implementado filtro inteligente baseado nos marcadores encontrados
   - Campo `relevante` adicionado aos polígonos retornados

2. **consultas/mapa_plot.php**
   - Filtro geoespacial para lotes da prefeitura
   - Função `calcularDistanciaSimples()` para proximidade
   - Estatísticas detalhadas sobre filtros aplicados
   - Logs informativos sobre decisões de filtragem

## Próximos Passos Sugeridos

1. **Raio Configurável**: Permitir ajustar o raio de proximidade via interface
2. **Filtros Adicionais**: Implementar filtros por tipo de imóvel, status, etc.
3. **Cache Inteligente**: Cachear resultados de filtros para consultas similares
4. **Visualização de Filtros**: Interface para mostrar/ocultar elementos filtrados
