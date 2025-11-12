# 📚 Sistema de Camadas Dinâmicas KML

## 🎯 Visão Geral

Este sistema permite carregar automaticamente arquivos KML da pasta `camadas/` e exibi-los no mapa com uma estrutura hierárquica similar ao Google Earth.

## ⚙️ Como Funciona

### 1. **Detecção Automática**
- O sistema varre a pasta `camadas/` em busca de arquivos `.kml`
- Cada arquivo KML encontrado é processado automaticamente
- Não é necessário configuração manual

### 2. **Estrutura Hierárquica**
```
📁 Camada Principal (nome do arquivo)
  ├── 📄 Subcamada 1 (agrupamento por nome)
  ├── 📄 Subcamada 2
  └── 📄 Subcamada 3
```

### 3. **Interface no Dropdown**
As camadas aparecem no menu "Camadas" com:
- 📂 **Seção "CAMADAS DINÂMICAS"** (separador visual)
- **>** Setinha de accordion (para expandir/retrair)
- ☑️ Checkbox da camada principal (em negrito)
- ☑️ Checkboxes das subcamadas (indentadas, dentro do accordion)

## 🎯 Accordion (Expandir/Retrair)

### Como Funciona
Cada camada dinâmica possui uma **setinha** (>) que controla a visibilidade das subcamadas:

- **> (Setinha para direita)** = Subcamadas **ocultas** (accordion fechado)
- **∨ (Setinha para baixo)** = Subcamadas **visíveis** (accordion aberto)

### Como Usar
1. Clique na **setinha** ao lado do nome da camada
2. As subcamadas aparecem/desaparecem com animação suave
3. O accordion usa o **Bootstrap Collapse** nativo

### 💡 Benefício
- Mantém o dropdown organizado quando há muitos KMLs
- Expande apenas as camadas que você está trabalhando
- Visual limpo e profissional

## 🔄 Comportamento dos Checkboxes

### Marcar Camada Principal
✅ **Marca** automaticamente todas as subcamadas
✅ **Mostra** todos os desenhos da camada no mapa

### Desmarcar Camada Principal
❌ **Desmarca** automaticamente todas as subcamadas
❌ **Oculta** todos os desenhos da camada do mapa

### Marcar/Desmarcar Subcamadas Individuais
- ☑️ Marca/desmarca subcamadas específicas
- 🔄 Atualiza o estado da camada principal:
  - **Desmarcado**: Nenhuma subcamada marcada
  - **Marcado**: Todas subcamadas marcadas
  - **Indeterminado**: Algumas subcamadas marcadas

## 📝 Preparando seus KMLs

### Estrutura Recomendada
```xml
<?xml version="1.0" encoding="UTF-8"?>
<kml xmlns="http://www.opengis.net/kml/2.2">
  <Document>
    <name>Nome da Camada</name>
    <Placemark>
      <name>Nome do Item</name>
      <description>Descrição opcional</description>
      <Polygon>
        <!-- Coordenadas -->
      </Polygon>
    </Placemark>
  </Document>
</kml>
```

### Agrupamento Automático
O sistema agrupa features por:
1. Propriedade `name`
2. Propriedade `Name`
3. Propriedade `description`
4. Propriedade `Description`

Features com o mesmo nome são agrupadas na mesma subcamada.

## 🎨 Estilos Suportados

### No KML
```xml
<Style id="meu_estilo">
  <LineStyle>
    <color>ff0000ff</color>  <!-- Vermelho -->
    <width>2</width>
  </LineStyle>
  <PolyStyle>
    <color>4d0000ff</color>  <!-- Vermelho semi-transparente -->
  </PolyStyle>
</Style>
```

### Cores no Google Maps
O sistema converte automaticamente as cores do KML para o formato do Google Maps.

## 📊 Tipos de Geometria

### ✅ Suportados
- **LineString** → Linha/Polilinha
- **Polygon** → Polígono
- **MultiLineString** → Múltiplas Linhas
- **MultiPolygon** → Múltiplos polígonos

### ❌ NÃO Suportados
- **Point** → Marcadores (serão ignorados)
- Outras geometrias complexas

### Propriedades Mapeadas
| KML | Google Maps | Padrão |
|-----|-------------|--------|
| `stroke` | `strokeColor` | `#FF0000` |
| `stroke-opacity` | `strokeOpacity` | `0.8` |
| `stroke-width` | `strokeWeight` | `2` |
| `fill` | `fillColor` | `#FF0000` |
| `fill-opacity` | `fillOpacity` | `0.35` |

## 🚀 Exemplo de Uso

### 1. Adicionar KML
```bash
# Coloque seu arquivo na pasta
cp minha_camada.kml camadas/
```

### 2. Recarregar Página
```
F5 ou Ctrl+R
```

### 3. Usar no Mapa
1. Abra o dropdown "Camadas"
2. Role até a seção **"CAMADAS DINÂMICAS"** (após o separador)
3. Encontre sua camada (ex: "minha camada")
4. **Clique na setinha >** para expandir e ver as subcamadas
5. Marque o checkbox principal para mostrar todos os desenhos
6. OU marque subcamadas individuais para controle granular

### 📺 Exemplo Visual

```
Dropdown "Camadas"
├── ☑️ Ortofoto
├── ☑️ Quadras
├── ☑️ Edificações
├── ━━━━━━━━━━━━━━━━━  (separador)
│
├── CAMADAS DINÂMICAS    ← Título da seção
│
├── [>] ☑️ Areas Verdes        ← Clique em > para expandir
│   (subcamadas ocultas)
│
├── [∨] ☐ Limites              ← Clique em ∨ para retrair
│   ├── ☐ Limite Norte
│   ├── ☐ Limite Sul
│   └── ☐ Limite Leste
│
└── [>] ☐ Zonas Especiais      ← Accordion fechado
    (subcamadas ocultas)
```

**Legenda:**
- `>` = Accordion fechado (subcamadas ocultas)
- `∨` = Accordion aberto (subcamadas visíveis)
- `☑️` = Marcado (visível no mapa)
- `☐` = Desmarcado (invisível no mapa)

## 🐛 Resolução de Problemas

### Camada não aparece
✅ Verifique se o arquivo está na pasta `camadas/`
✅ Verifique se a extensão é `.kml`
✅ Abra o console do navegador (F12) e procure por erros

### Desenhos não aparecem
✅ Verifique se o checkbox está marcado
✅ Verifique se as coordenadas estão corretas
✅ Verifique se o zoom do mapa está adequado

### Subcamadas vazias
✅ Verifique se as features têm propriedade `name`
✅ Adicione nomes descritivos às suas features

## 📁 Estrutura de Arquivos

```
projeto/
├── camadas/
│   ├── README.md
│   ├── exemplo_test.kml
│   ├── areas_verdes.kml
│   └── limites.kml
├── framework.js (função carregarMaisCamadas)
├── listar_kmls.php (lista arquivos da pasta)
└── index_3.php (interface principal)
```

## 💡 Dicas

1. **Nomes de Arquivo**: Use underscores, eles viram espaços
   - `areas_verdes.kml` → "areas verdes"

2. **Organização**: Use nomes descritivos nas features
   ```xml
   <name>Parque Municipal 1</name>
   <name>Parque Municipal 2</name>
   ```

3. **Performance**: Evite KMLs muito grandes (>5MB)
   - Divida em múltiplos arquivos se necessário

4. **Coordenadas**: Use o formato longitude,latitude,altitude
   ```xml
   <coordinates>-47.0,-22.7,0</coordinates>
   ```

5. **Accordion**: Mantenha fechado o que não está usando
   - Evita rolagem excessiva no dropdown
   - Foco visual nas camadas relevantes
   - Expanda apenas quando precisar ver as subcamadas

6. **Separador Visual**: As camadas dinâmicas ficam após o separador
   - Facilita identificar onde começam as camadas do KML
   - Título "CAMADAS DINÂMICAS" deixa claro a origem

## 🎓 Exemplos Avançados

### KML com Múltiplas Subcamadas
```xml
<Document>
  <Placemark>
    <name>Área Tipo A</name>
    <!-- ... -->
  </Placemark>
  <Placemark>
    <name>Área Tipo A</name>  <!-- Mesmo grupo -->
    <!-- ... -->
  </Placemark>
  <Placemark>
    <name>Área Tipo B</name>  <!-- Outro grupo -->
    <!-- ... -->
  </Placemark>
</Document>
```

Resultado:
- 📁 Nome do Arquivo
  - ☑️ Área Tipo A (2 itens)
  - ☑️ Área Tipo B (1 item)

## 🔧 Manutenção

### Adicionar Nova Camada
1. Copie o arquivo KML para `camadas/`
2. Recarregue a página

### Remover Camada
1. Delete o arquivo KML de `camadas/`
2. Recarregue a página

### Atualizar Camada
1. Substitua o arquivo KML
2. Recarregue a página

## ⚠️ Limitações

- KML deve estar em UTF-8
- Máximo recomendado: 100 features por arquivo
- Não suporta KMZ (apenas KML)
- Não suporta imagens inline
- **Não desenha Points (marcadores)** - apenas polígonos e linhas
- Não suporta geometrias 3D ou arcos

## 📞 Suporte

Para problemas ou dúvidas:
1. Verifique o console do navegador (F12)
2. Verifique a estrutura do KML
3. Teste com o `exemplo_test.kml` incluído

