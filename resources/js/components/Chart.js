// Classe que constrói e renderiza gráficos usando ECharts
class Chart {
  setId(id)    { this._id   = id;    return this; }
  getData(url) { this._url  = url;   return this; }
  BAR()        { this._type = 'bar'; return this; }
  PIE()        { this._type = 'pie'; return this; }


  // busca os dados da API e renderiza o gráfico
  async render() {
    const data  = await fetch(this._url).then(r => r.json());
    const chart = window.echarts.init(document.getElementById(this._id)); // usa window.echarts
    chart.setOption(this._type === 'pie' ? buildPie(data) : buildBar(data));
  }
}

// monta a opção do ECharts para gráfico de barras
// a API deve retornar: { categories: ['Jan','Fev',...], values: [10, 20, ...] }
function buildBar(data) {
  return {
    tooltip: {},
    xAxis: { data: data.categories },
    yAxis: {},
    series: [{ type: 'bar', data: data.values }]
  };
}

// monta a opção do ECharts para gráfico de pizza
// a API deve retornar: { series: [{ name: 'X', value: 100 }, ...] }
function buildPie(data) {
  return {
    tooltip: { trigger: 'item' },
    series: [{ type: 'pie', radius: ['40%', '70%'], data: data.series }]
  };
}

// exporta uma única instância reutilizável
export default Chart;