/* LER BEM - interacoes de interface (JavaScript puro) */
(function () {
  'use strict';

  var LB = window.LB = window.LB || {};
  LB.url = function (path) {
    var base = document.body.getAttribute('data-base') || '';
    return base + '/' + String(path).replace(/^\/+/, '');
  };
  LB.csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

  function $(sel, ctx) { return (ctx || document).querySelector(sel); }
  function $$(sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); }

  /* ---- Sidebar mobile ---- */
  function sidebar(open) {
    var el = $('#sidebar');
    var scrim = $('.sidebar-scrim');
    if (!el) return;
    el.classList.toggle('is-open', open);
    if (scrim) scrim.classList.toggle('is-open', open);
    document.body.style.overflow = open ? 'hidden' : '';
  }
  document.addEventListener('click', function (ev) {
    if (ev.target.closest('[data-sidebar-open]')) { sidebar(true); }
    if (ev.target.closest('[data-sidebar-close]')) { sidebar(false); }
    var dismiss = ev.target.closest('[data-dismiss]');
    if (dismiss) {
      var alert = dismiss.closest('.alert');
      if (alert) alert.remove();
    }
  });

  /* ---- Confirmacao antes de excluir ---- */
  document.addEventListener('submit', function (ev) {
    var form = ev.target;
    if (form.matches('[data-confirm]')) {
      if (!window.confirm(form.getAttribute('data-confirm') || 'Confirmar esta acao?')) {
        ev.preventDefault();
        return;
      }
    }
    if (form.matches('[data-loading]')) {
      var btn = form.querySelector('button[type="submit"], button:not([type])');
      if (btn) { btn.classList.add('is-loading'); btn.setAttribute('aria-busy', 'true'); }
    }
  });

  /* ---- Auto-ocultar mensagens ---- */
  window.setTimeout(function () {
    $$('.alert-success').forEach(function (a) {
      a.style.transition = 'opacity .4s';
      a.style.opacity = '0';
      window.setTimeout(function () { a.remove(); }, 420);
    });
  }, 7000);

  /* ---- Abas ---- */
  $$('[data-tab]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var group = btn.closest('.tabs');
      var alvo = btn.getAttribute('data-tab');
      if (group) {
        $$('.tab', group).forEach(function (b) { b.classList.toggle('is-active', b === btn); });
      }
      $$('.tab-panel').forEach(function (p) {
        if (p.getAttribute('data-panel') === alvo) { p.removeAttribute('hidden'); }
        else if (p.hasAttribute('data-panel')) { p.setAttribute('hidden', ''); }
      });
    });
  });

  /* ---- Selecao de escola -> turmas (area do aluno) ---- */
  function initEscolaTurmas() {
    var escola = $('#escola_id');
    var turma = $('#turma_id');
    if (!escola || !turma) return;
    var placeholder = turma.getAttribute('data-placeholder') || 'Selecione a turma';
    escola.addEventListener('change', function () {
      turma.innerHTML = '<option value="">Carregando turmas...</option>';
      turma.disabled = true;
      fetch(LB.url('api/turmas.php') + '?escola_id=' + encodeURIComponent(escola.value), {
        headers: { 'Accept': 'application/json' }
      }).then(function (r) { return r.json(); }).then(function (data) {
        turma.innerHTML = '<option value="">' + placeholder + '</option>';
        (data.turmas || []).forEach(function (t) {
          var o = document.createElement('option');
          o.value = t.id;
          o.textContent = t.nome + ' · ' + t.ano_letivo;
          turma.appendChild(o);
        });
        turma.disabled = false;
        if (!(data.turmas || []).length) {
          turma.innerHTML = '<option value="">Nenhuma turma ativa nesta escola</option>';
        }
      }).catch(function () {
        turma.innerHTML = '<option value="">Falha ao carregar turmas</option>';
        turma.disabled = false;
      });
    });
  }

  /* ---- Contador de caracteres ---- */
  $$('[data-counter]').forEach(function (area) {
    var alvo = document.getElementById(area.getAttribute('data-counter'));
    if (!alvo) return;
    var max = parseInt(area.getAttribute('maxlength') || '0', 10);
    var atualizar = function () {
      alvo.textContent = area.value.length + (max ? ' / ' + max : '') + ' caracteres';
    };
    area.addEventListener('input', atualizar);
    atualizar();
  });

  /* ---- IA: geracao de perguntas ---- */
  function initIA() {
    var form = $('#form-ia');
    if (!form) return;
    var out = $('#ia-resultado');
    var btn = $('#btn-ia');
    form.addEventListener('submit', function (ev) {
      ev.preventDefault();
      if (btn) { btn.classList.add('is-loading'); btn.disabled = true; }
      out.innerHTML = '<div class="alert alert-info">Consultando o modulo de IA... isso pode levar alguns segundos.</div>';
      var fd = new FormData(form);
      fetch(LB.url('api/ai_gerar.php'), {
        method: 'POST',
        body: fd,
        headers: { 'X-CSRF-Token': LB.csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
      }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, body: j }; }); })
        .then(function (res) {
          if (!res.ok || !res.body.ok) {
            out.innerHTML = '<div class="alert alert-error">' + escapeHtml(res.body.erro || 'Nao foi possivel gerar as perguntas.') + '</div>';
            return;
          }
          renderPerguntasIA(res.body.perguntas || []);
        })
        .catch(function () {
          out.innerHTML = '<div class="alert alert-error">Falha de comunicacao com o modulo de IA. Tente novamente.</div>';
        })
        .finally(function () {
          if (btn) { btn.classList.remove('is-loading'); btn.disabled = false; }
        });
    });

    function renderPerguntasIA(lista) {
      if (!lista.length) {
        out.innerHTML = '<div class="alert alert-warning">A IA nao retornou perguntas. Ajuste o conteudo e tente novamente.</div>';
        return;
      }
      var html = '<div class="alert alert-success">' + lista.length + ' perguntas geradas. Revise, edite e aprove antes de salvar.</div>';
      lista.forEach(function (p, i) {
        html += '<div class="pergunta"><div class="pergunta-head"><span class="pergunta-num">' + (i + 1) + '</span>'
          + '<div style="flex:1"><div class="pergunta-texto">' + escapeHtml(p.enunciado || '') + '</div>'
          + '<div class="pergunta-tipo">' + labelTipo(p.tipo) + '</div></div>'
          + '<button type="button" class="icon-btn" data-remover-ia aria-label="Remover">✕</button></div>';
        if (p.tipo === 'aberta') {
          html += '<div class="explicacao">Resposta aberta — correcao manual pelo professor.</div>';
        } else {
          (p.alternativas || []).forEach(function (a) {
            html += '<div class="alt"><input type="radio" disabled' + (a.correta ? ' checked' : '') + '><span>'
              + escapeHtml(a.texto) + (a.correta ? ' <strong>(correta)</strong>' : '') + '</span></div>';
          });
        }
        if (p.explicacao) html += '<div class="explicacao"><strong>Explicacao:</strong> ' + escapeHtml(p.explicacao) + '</div>';
        html += '</div>';
      });
      out.innerHTML = html;
      $$('[data-remover-ia]', out).forEach(function (b) {
        b.addEventListener('click', function () { b.closest('.pergunta').remove(); });
      });
    }

    function labelTipo(t) {
      return t === 'vf' ? 'Verdadeiro ou falso' : (t === 'aberta' ? 'Resposta aberta' : 'Multipla escolha');
    }
    function escapeHtml(s) {
      return String(s).replace(/[&<>"']/g, function (c) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
      });
    }
  }

  /* ---- Graficos ---- */
  function initCharts() {
    if (typeof window.Chart === 'undefined') return;
    $$('canvas[data-chart]').forEach(function (cv) {
      var cfg;
      try { cfg = JSON.parse(cv.getAttribute('data-chart')); } catch (e) { return; }
      if (!cfg || !cfg.type) return;
      cfg.options = Object.assign({
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: cfg.type === 'doughnut' || cfg.type === 'pie', position: 'bottom', labels: { boxWidth: 12, usePointStyle: true, font: { size: 12 } } },
          tooltip: { backgroundColor: '#073c3a', padding: 10, cornerRadius: 8 }
        },
        scales: cfg.type === 'doughnut' || cfg.type === 'pie' ? undefined : {
          x: { grid: { display: false }, ticks: { color: '#6b7f92', font: { size: 11 } } },
          y: { beginAtZero: true, grid: { color: '#eef3f2' }, ticks: { color: '#6b7f92', precision: 0, font: { size: 11 } } }
        }
      }, cfg.options || {});
      new window.Chart(cv, cfg);
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    initEscolaTurmas();
    initIA();
    window.setTimeout(initCharts, 120);
    if (window.Chart) initCharts();
    $$('form[data-loading]').forEach(function (f) { f.addEventListener('input', function () {}); });
  });
  window.addEventListener('load', initCharts);
})();
