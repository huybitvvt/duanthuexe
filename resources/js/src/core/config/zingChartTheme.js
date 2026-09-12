export const zingChartTheme = {
    palette: {
        line: [
            ['#FBFCFE', '#00BAF2', '#00BAF2', '#00a7d9'], /* light blue */
            ['#FBFCFE', '#E80C60', '#E80C60', '#d00a56'], /* light pink */
            ['#FBFCFE', '#9B26AF', '#9B26AF', '#8b229d'], /* light purple */
            ['#FBFCFE', '#E2D51A', '#E2D51A', '#E2D51A'], /* med yellow */
            ['#FBFCFE', '#FB301E', '#FB301E', '#e12b1b'], /* med red */
            ['#FBFCFE', '#00AE4D', '#00AE4D', '#00AE4D'], /* med green */
        ]
    },
    graph: {
        backgroundColor: '#FBFCFE',
        title: {
            fontFamily: 'SVN-Gilroy',
            fontSize: 20,
            // border: "1px solid black",
            padding: "15",
            fontColor: "#1E5D9E",
            adjustLayout: true
        },
        subtitle: {
            fontFamily: 'SVN-Gilroy',
            fontSize: 20,
            fontColor: "#777",
            padding: "5"
        },
        plot: {
            backgroundColor: '#FBFCFE',
            marker: {
                size: 4
            }
        },
        tooltip: {
            visible: true,
            text: "%kl<br><span style='color:%color'>%t: </span>%v<br>",
            backgroundColor: "white",
            borderColor: "#e3e3e3",
            borderRadius: "5px",
            bold: true,
            fontSize: "12px",
            fontColor: "#2f2f2f",
            textAlign: 'left',
            padding: '15px',
            shadow: true,
            shadowAlpha: 0.2,
            shadowBlur: 5,
            shadowDistance: 4,
            shadowColor: "#a1a1a1"
        },
        plotarea: {
            backgroundColor: 'transparent',
            borderRadius: "0 0 0 10",
            borderRight: "0px",
            borderTop: "0px",
            margin: "dynamic",
            borderLeft: '1px solid #1E5D9E',
            borderBottom: '1px solid #1E5D9E',
        },
        scaleX: {
            zooming: true,
            offsetY: -20,
            lineWidth: 0,
            padding: 20,
            margin: 20,
            label: {
                text: "Scale-X"
            },
            item: {
                padding: 5,
                fontColor: "#1E5D9E",
                fontFamily: 'SVN-Gilroy',
                fontSize: "18px",
                rules: [{
                    rule: "%i == 0",
                    visible: false
                }]
            },
            tick: {
                lineColor: '#D1D3D4',
                rules: [{
                    rule: "%i == 0",
                    visible: false
                }]
            },
            guide: {
                visible: true,
                lineColor: '#D7D8D9',
                lineStyle: 'dotted',
                lineGapSize: '4px',
                rules: [{
                    rule: "%i == 0",
                    visible: false
                }]
            }
        },
        scaleY: {
            zooming: true,
            lineWidth: 0,
            label: {
                text: "Scale-Y"
            },
            item: {
                padding: "0 10 0 0",
                fontColor: "#1E5D9E",
                fontFamily: 'SVN-Gilroy',
                fontSize: "18px",
                rules: [{
                    rule: "%i == 0",
                    offsetX: 10,
                    offsetY: 10,
                    backgroundColor: 'none'
                }]
            },
            tick: {
                lineColor: '#D1D3D4',
                rules: [{
                    rule: "%i == 0",
                    visible: false
                }]
            },
            guide: {
                visible: true,
                lineColor: '#D7D8D9',
                lineStyle: 'dotted',
                lineGapSize: '4px',
                rules: [{
                    rule: "%i == 0",
                    visible: false
                }]
            }
        },
        scrollX: {
            bar: {
                backgroundColor: 'none',
                borderLeft: 'none',
                borderTop: 'none',
                borderBottom: 'none'
            },
            handle: {
                backgroundColor: '#1E5D9E',
                height: 5
            }
        },
        scrollY: {
            borderWidth: 0,
            bar: {
                backgroundColor: 'none',
                width: 14,
                borderLeft: 'none',
                borderTop: 'none',
                borderBottom: 'none'
            },
            handle: {
                borderWidth: 0,
                backgroundColor: '#1E5D9E',
                width: 5
            }
        },
        zoom: {
            backgroundColor: '#1E5D9E',
            alpha: .33,
            borderColor: '#000',
            borderWidth: 1
        },
        preview: {
            borderColor: '#1E5D9E',
            borderWidth: 1,
            adjustLayout: true,
            handle: {
                backgroundColor: '#1E5D9E',
                borderColor: '#1E5D9E'
            },
            mask: {
                backgroundColor: '#FFF',
                alpha: .95,
            }
        }
    }
};
